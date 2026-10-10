# Nursery Check-In API

A multi-tenant REST API built with Laravel and MySQL for managing nursery room check-ins and check-outs.

## Requirements

* Docker Desktop
* Git
* PHP and Composer for installing Laravel dependencies, if they are not already installed
* Postman or another HTTP client for testing the API

The application uses Laravel Sail to run its development environment in Docker.

## 1. Project setup

Clone the repository and navigate to the project directory:

```bash
git clone <repository-url>
cd nursery-checkin-api
```

Install PHP dependencies if they are not already present:

```bash
composer install
```

Create your environment file:

```bash
cp .env.example .env
```

Ensure the database configuration in `.env` matches the Sail configuration:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Use the actual credentials defined by your Docker Compose configuration if they differ.

Start the Docker containers:

```bash
./vendor/bin/sail up -d
```

Generate the Laravel application key:

```bash
./vendor/bin/sail artisan key:generate
```

Run the database migrations:

```bash
./vendor/bin/sail artisan migrate
```

The API is available at:

`http://localhost/api`

If your Docker Compose configuration maps the application to a different host port, use that port instead.

## 2. Multi-tenancy

Every API request must include the `X-Tenant-ID` header.

Example:

```http
X-Tenant-ID: 1
Accept: application/json
```

The middleware resolves the tenant from this header. Requests with a missing or invalid tenant ID are rejected.

Rooms, children, and check-in records are scoped to the resolved tenant. A tenant cannot use another tenant's room or child, view another tenant's occupants, or check out another tenant's check-in.

## 3. API endpoints

### Check a child into a room

`POST /api/rooms/{room}/check-ins`

Request body:

```json
{
  "child_id": 1
}
```

Creates a check-in for the specified child and room.

A child who already has an active check-in cannot be checked in again, even into another room. The child must first be checked out.

### Check a child out

`POST /api/check-ins/{id}/check-out`

Checks out an active check-in. The check-in must belong to the tenant identified by `X-Tenant-ID`.

A check-in that has already been checked out cannot be checked out again.

### List current room occupants

`GET /api/rooms/{room}/occupants`

Returns children with active check-ins for the specified room and tenant. Checked-out children are excluded.

## 4. Database setup and test data

The application uses MySQL through Laravel Sail.

For manual API testing, create at least two tenants, their rooms, and their children. Keep the records assigned to their respective tenants.

If the repository includes the `NurseryTestSeeder` seeder, it can be run using:

```bash
./vendor/bin/sail artisan db:seed --class=NurseryTestSeeder
```

Use the IDs returned by the seeder in your API requests. The automated feature tests create their own test data.

## 5. Automated tests

Automated feature tests cover successful check-ins and check-outs, request validation, tenant isolation, duplicate active check-ins, repeated check-outs, and room occupant listing.

Tests use a dedicated MySQL database named `nursery_checkin_test`, separate from the development database.

Create the test database once in MySQL:

```sql
CREATE DATABASE nursery_checkin_test;
GRANT ALL PRIVILEGES ON nursery_checkin_test.* TO 'sail'@'%';
```

Execute these statements as a MySQL administrator. If the database already exists, only the required permissions need to be granted.

Configure the test environment in `.env.testing`:

```dotenv
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=nursery_checkin_test
DB_USERNAME=sail
DB_PASSWORD=password
```

Ensure `phpunit.xml` does not override these settings with SQLite or the development database.

Run the feature tests:

```bash
./vendor/bin/sail artisan test --filter=NurseryCheckInTest
```

**Important:** The test suite uses Laravel's `RefreshDatabase` trait. Never configure the test environment to use the development database.

## 6. Postman testing

Import the Postman collection provided with the project, if included in the repository.

Configure the base URL and tenant, room, and child IDs to match your local database. Include `X-Tenant-ID` in every request.

Test both successful requests and failure scenarios, especially attempts to access resources belonging to another tenant.

## 7. Design decisions

* **Tenant middleware:** Resolves the tenant once per request and makes it available to the application.
* **Form Request validation:** Validates request input and verifies child ownership.
* **Service classes:** Keep check-in, check-out, and occupant retrieval logic outside controllers.
* **Database transactions and row locking:** Help prevent concurrent requests from creating duplicate active check-ins for the same child.
* **Tenant-scoped queries:** Prevent access to records owned by another tenant.
* **Automated feature tests:** Verify the API behaviour and tenant isolation.

A child is allowed only one active check-in at a time. A new check-in is permitted after the existing check-in has been closed.

## 8. AWS ECS/Fargate deployment approach

I have limited hands-on experience with deploying applications to AWS. I used AI assistance to explore a possible deployment architecture for this application and understand the main AWS services involved. The approach below reflects my current understanding, but I have not independently verified every configuration detail or implemented the deployment end to end. It should be treated as a proposed approach, not a production-ready implementation.

The application could potentially run on Amazon ECS using Fargate, with a Docker image stored in Amazon ECR. An Application Load Balancer could route incoming requests to the application containers, while Amazon RDS for MySQL could provide the database.

Sensitive configuration, such as database credentials and application secrets, could be managed using AWS Secrets Manager and supplied to the application at runtime. The database should be accessible only through appropriate private networking and security group rules.

Application logs could be sent to Amazon CloudWatch Logs, with alarms configured for relevant errors, resource utilisation, and service health. ECS health checks and deployment health checks could help identify unhealthy tasks and reduce the risk of deploying a broken version.

Database migrations would be run as a controlled deployment step rather than independently by every application container. Access to AWS resources should follow least-privilege principles.

## 9. Scope and limitations

The implementation focuses on the three requested API operations, tenant isolation, validation, and automated tests.

Authentication, user management, room and child CRUD endpoints, frontend interfaces, production infrastructure, and actual AWS deployment are outside the current scope.

## 10. AI tool usage

ChatGPT was used to help interpret the assignment requirements, discuss implementation and testing approaches, and troubleshoot the local development environment. The implementation and test results were reviewed and run locally before submission.

## 11. Check-In Decision and Concurrency Handling

**`Business rule`**

A child can have only one active check-in at a time, regardless of which room they are in. If staff attempt to check the child into another room without first checking them out, the API rejects the request. This avoids silently moving a child and keeps the check-in history explicit and predictable.

**`Implementation`**

The check-in operation uses a database transaction and a row-level lock to help prevent concurrent requests from checking the same child into multiple rooms.

The process is:

Start a database transaction.
Retrieve the child belonging to the current tenant and acquire a row lock using Laravel's lockForUpdate().
Check whether the child already has an active check-in, meaning checked_out_at is NULL.
If an active check-in exists, reject the request. Otherwise, create the new check-in.
Commit the transaction.
Locking strategy

The implementation uses a pessimistic row-level lock through MySQL's SELECT ... FOR UPDATE, exposed by Laravel's lockForUpdate() method.

The child record is locked rather than locking the entire table. If two requests try to check in the same child concurrently, the second request must wait for the first transaction to release the lock. Once it proceeds, it checks for an existing active check-in before creating another one.

The transaction ensures the check and insert happen within the same transactional operation. The lock serialises competing check-in requests for the same child, while requests involving different children can generally proceed independently.

This protection relies on all check-in operations following the same locking procedure and on MySQL using a transactional storage engine such as InnoDB. It is application-level concurrency protection; a database-level constraint or additional safeguards could be considered if stronger guarantees against writes outside this service were required.

## 12. Possible Improvements and Deliberate Trade-offs

Given more time, I would consider the following improvements. For this assignment, I prioritised the required functionality, tenant isolation, readability, and automated tests over adding extra layers of abstraction or infrastructure.

1. Centralised tenant scoping

Currently, tenant filtering is handled explicitly in queries. This makes tenant ownership visible and easy to follow, but it also means every new query must remember to apply the correct tenant condition.

For a larger application, I would consider introducing a reusable tenant trait with an Eloquent global scope to apply tenant filtering automatically. I would also introduce a dedicated tenant context to make the current tenant available consistently across HTTP requests and background jobs.

I would ensure that missing tenant context fails closed, and add tests for relationships, eager loading, record creation, and attempts to bypass the scope. Explicit tenant checks and database constraints would still be important; a global scope alone should not be treated as a complete security boundary.

2. More comprehensive automated tests

The current feature tests cover the main API behaviour, validation failures, and tenant isolation. I would extend them to cover concurrent check-in attempts, transaction rollback on failure, additional malformed inputs, and tenant isolation through model relationships.

I would also add tests for missing tenant context in non-HTTP execution paths if the application later supports background jobs or scheduled tasks.

3. API response consistency and documentation

With more time, I would standardise error response formats and document the endpoints, request headers, validation rules, status codes, and example responses using an OpenAPI specification.

This would make the API easier for other developers and client applications to integrate with.