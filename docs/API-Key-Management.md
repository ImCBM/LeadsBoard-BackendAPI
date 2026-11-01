API Key Management Architecture

Architecture Decisions, Design Principles, and Implementation Guidelines

Document Type: Technical Architecture Reference
Status: Proposed Architecture
Technology Stack: Laravel (Backend/API), React (Frontend)
Purpose: Establish a centralized, secure, and maintainable API key management architecture.

---

1. Overview

The application consists of two separate systems:

1. Laravel Backend: Responsible for business logic, database management, authentication, authorization, API key generation, subscription management, and API services. It also provides an internal administrative GUI.
2. React Frontend: A customer-facing application where users access the platform, manage their accounts, view subscription information, and generate and manage their own API keys.

Both systems communicate with the same Laravel backend.

The application supports different types of users, including:

- Internal administrators.
- Developers.
- Testers.
- Individual customers.
- Enterprise customers and their authorized members.

The primary architectural objective is to maintain a centralized API key management system while providing different interfaces and levels of authority for internal personnel and external customers.

2. Core Architectural Decision

Two interfaces, one centralized API key management system.

The application will retain two separate API key management interfaces:

A. Internal Administrative Interface (Laravel GUI)

Used by internal developers, testers, and administrators.

Responsibilities include:

- Generating API keys for internal development and testing.
- Managing API keys.
- Configuring permissions.
- Managing accounts and subscriptions, subject to assigned roles.
- Managing permission policies.
- Revoking API keys.
- Reviewing audit logs.
- Performing approved administrative key management operations.

B. Customer Interface (React)

Used by external customers.

Responsibilities include:

- Generating their own API keys.
- Viewing existing API keys.
- Revoking their own API keys.
- Selecting available permissions.
- Viewing subscription-based access restrictions.
- Monitoring API usage and applicable limits.

The two interfaces may expose similar functionality, but they must not independently implement API key generation, permission assignment, or validation.

Both interfaces must use the same underlying Laravel business logic and API key management services.

Architectural principle:

Different interfaces and authorization contexts are acceptable. Duplicated business logic and independent sources of truth are not.

3. System Architecture

The intended architecture is:

                 REACT FRONTEND
                 Customer Portal
                       |
                       |
                Customer API
                       |
                       |
                       v
+--------------------------------------------+
|               LARAVEL BACKEND              |
|                                            |
|  +------------------+  +----------------+  |
|  | Customer API     |  | Admin GUI      |  |
|  |                  |  |                |  |
|  | Customer access  |  | Internal       |  |
|  | Key management   |  | administration |  |
|  +------------------+  +----------------+  |
|                                            |
|  +--------------------------------------+  |
|  |       Shared Business Logic          |  |
|  |                                      |  |
|  | API Key Management Service           |  |
|  | Permission / Authorization Service   |  |
|  | Subscription / Entitlement Service   |  |
|  | Authentication Service               |  |
|  | Audit Logging Service                |  |
|  +--------------------------------------+  |
|                                            |
+--------------------------------------------+
                       |
                       v
                SHARED DATABASE
                       |
       +---------------+----------------+
       |               |                |
     Users         API Keys       Subscriptions
       |               |                |
 Organizations    Permissions       Entitlements
                       |
                  Audit Logs

The Laravel backend remains the authoritative source of truth.

The React application must not independently determine or enforce permissions. All security-sensitive decisions must be validated by Laravel.

4. API Key Ownership

API keys must have explicit ownership.

The system must support two ownership models:

4.1 Individual ownership

Regular customers own their API keys through their individual accounts.

A customer can:

- Create their own keys.
- View their own keys.
- Revoke their own keys.
- Configure permissions within their subscription entitlements.

One customer must never be able to access or manage another customer's keys.

4.2 Organization ownership

Enterprise customers will use organization-level ownership.

An organization can have:

- An organization subscription.
- Organization-level entitlements.
- Multiple authorized members.
- Organization-owned API keys.
- Organization-level key management policies.

The Enterprise onboarding process will initially follow an inquiry and approval workflow.

Once approved, an organization account can be established and assigned its subscription and entitlements.

API keys should belong to the organization rather than being tied exclusively to the individual employee who generated them.

The system should maintain records of the individual who created or modified each key.

5. Permission and Entitlement Architecture

The permission model is one of the most important components of the application.

The system must distinguish between:

1. Subscription entitlements.
2. API key permissions.
3. Effective permissions.

5.1 Subscription Entitlements

Subscription entitlements define the maximum access available to a user or organization.

For example:

Resource / Field| Basic| Pro| Enterprise
First Name - Read| Yes| Yes| Yes
First Name - Write| No| Yes| Yes
Last Name - Read| Yes| Yes| Yes
Last Name - Write| No| Yes| Yes
Email - Read| Yes| Yes| Yes
Email - Write| No| Yes| Yes
Phone - Read| No| Yes| Yes
Phone - Write| No| Yes| Yes

The table is illustrative. Actual permissions must be defined by the product's subscription policies.

Entitlements determine what an account is eligible to access.

5.2 API Key Permissions

Customers can create multiple API keys with different permission configurations.

A customer may choose any combination of permissions that their account is entitled to use.

Example:

A Basic customer has access to read first name, last name, and email.

They may generate:

API Key| First Name| Last Name| Email
Key A| Read| Read| Read
Key 😎 Read| None| None
Key C| None| None| Read

A Pro customer may generate:

API Key| First Name| Last Name| Email
Key A| Read + Write| Read| Read
Key 😎 Write| Write| None
Key C| Read| None| Write

Customers can restrict their keys to a smaller permission set, but cannot grant themselves permissions outside their subscription.

5.3 Effective Permissions

The system must calculate effective permissions using the intersection of subscription entitlements and API key permissions.

Conceptually:

Effective Permissions =
    Subscription Entitlements
    INTERSECT
    API Key Permissions

For example:

If a customer's API key allows writing to the email field, but their subscription does not allow email write access, the effective permission must deny that operation.

Likewise, if the customer has permission to write email but generates a read-only key, that key must not be allowed to write email.

The API key must never grant greater access than the account's subscription.

6. Authorization Requirements

All protected API requests must be validated by the Laravel backend.

The authorization process should follow this sequence:

1. Validate the API key.
2. Verify that the key is active.
3. Verify that the key has not expired.
4. Identify the key owner (individual or organization).
5. Verify the owner's account status.
6. Verify current subscription entitlements.
7. Verify API key permissions.
8. Verify resource-level permissions.
9. Verify field-level permissions.
10. Execute the operation only if authorization succeeds.

Authorization must be enforced on both read and write operations.

Important: Response Filtering

Field-level permissions must apply to API responses.

If an API key has access only to the "first_name" field, the API must not return "email", "phone", or other unauthorized fields.

Important: Write Validation

The API must validate submitted fields individually.

Unauthorized fields must be rejected or safely excluded according to the application's documented API behavior.

Never rely on frontend restrictions or mass-assignment protection alone as the complete authorization mechanism.

7. Subscription Changes and Permission Updates

API key permissions must be evaluated against the owner's current subscription.

Example:

A Pro customer generates a key with the following permissions:

- First Name: Read
- Last Name: Read
- Email: Read
- Phone: Write

The customer later downgrades to Basic.

If Basic does not include phone access, the key must immediately lose effective access to the phone field.

The stored key configuration may remain unchanged, but effective permissions must reflect the current subscription.

Similarly, upgrading a subscription must not automatically grant every existing API key new permissions.

The key must still explicitly include the desired permissions.

This prevents accidental privilege escalation.

8. Internal Administrative Roles

Internal personnel require different authorization policies from customers.

Suggested roles:

Role| Responsibilities
Super Admin| Full administrative control
Developer| Approved development and testing operations
Tester| Restricted testing access
Customer| Manage their own API keys
Enterprise Admin| Manage organization-owned API keys

These roles are initial recommendations and should be refined according to actual operational requirements.

Internal administrative privileges must not automatically grant unrestricted access to production customer data.

Administrative actions must be logged.

8.1 Internal Key Creation

Internal developers should primarily create keys for internal development and testing.

If internal personnel need to assist a customer with API key management, the key must:

- Belong to the correct customer or organization.
- Inherit the owner's subscription restrictions.
- Follow the same permission validation rules.
- Be recorded in the audit logs.
- Be visible to the customer where appropriate.

Internal personnel must not bypass subscription entitlements simply because they have access to the administrative interface.

9. Security Requirements

The following security requirements should guide implementation.

9.1 API Key Storage

- Generate cryptographically secure random API keys.
- Store only a secure hash of the API key.
- Never store plaintext API keys.
- Display the complete API key only once during generation.
- Display masked API keys in subsequent views.
- Support key revocation.
- Support key expiration where applicable.

9.2 Authentication

The current development implementation uses simple username and password authentication.

This is acceptable as a temporary development arrangement, provided access is restricted appropriately.

Before production:

- Implement stronger authentication controls.
- Require MFA for internal administrators.
- Use secure password hashing.
- Implement session security and appropriate CSRF protection.
- Apply login rate limiting.
- Enforce appropriate account access policies.

Google authentication or another identity provider may be introduced later, but it is not a requirement for the centralized API key architecture.

9.3 Environment Separation

Maintain separate environments:

- Development
- Staging
- Production

API keys should be restricted to their intended environment.

Development credentials must never provide accidental access to production resources.

9.4 Audit Logging

Record important events, including:

- API key creation.
- API key revocation.
- Permission changes.
- Key expiration.
- Administrative key generation.
- Administrative actions performed on customer accounts.
- Relevant authentication and authorization failures.

Audit records should identify the actor, affected account, affected key, timestamp, and action.

Never log plaintext API keys.

10. Suggested Laravel Code Organization

The backend should centralize business logic in dedicated services.

Suggested organization:

app/
|
+-- Services/
|   |
|   +-- ApiKeyService.php
|   +-- PermissionService.php
|   +-- EntitlementService.php
|   +-- AuthorizationService.php
|   +-- AuditLogService.php
|
+-- Models/
|   |
|   +-- User.php
|   +-- Organization.php
|   +-- ApiKey.php
|   +-- ApiKeyPermission.php
|   +-- Subscription.php
|   +-- SubscriptionEntitlement.php
|   +-- AuditLog.php
|
+-- Http/
    |
    +-- Controllers/
    |   |
    |   +-- Api/
    |   |   +-- CustomerApiKeyController.php
    |   |
    |   +-- Admin/
    |       +-- AdminApiKeyController.php
    |
    +-- Middleware/
        |
        +-- ValidateApiKey.php
        +-- CheckApiPermissions.php

This is a suggested organization, not a requirement to create every class immediately.

Controllers should handle HTTP concerns and delegate business operations to the appropriate services.

The same underlying service should be reusable by both internal administrative operations and customer-facing API operations, with appropriate authorization checks at their respective entry points.

11. Suggested Database Structure

Initial conceptual entities:

Table| Purpose
users| Individual user accounts
organizations| Enterprise organizations
organization_members| Organization membership and roles
subscriptions| Customer and organization subscriptions
subscription_entitlements| Permissions granted by subscription
api_keys| API key records and ownership
api_key_permissions| Permissions assigned to individual keys
audit_logs| Administrative and security activity

API key ownership should support either an individual account or an organization.

Enforce that a key has exactly one valid owner.

Database relationships, indexes, foreign keys, and deletion behavior must be designed to preserve ownership and historical audit records.

12. API Endpoint Organization

Suggested endpoint separation:

Customer-facing endpoints

GET    /api/user/api-keys
POST   /api/user/api-keys
GET    /api/user/api-keys/{id}
DELETE /api/user/api-keys/{id}

GET    /api/user/available-permissions
GET    /api/user/subscription

These endpoints operate within the authenticated customer's ownership and entitlement restrictions.

Internal administrative endpoints

GET    /admin/api-keys
POST   /admin/api-keys
GET    /admin/api-keys/{id}
DELETE /admin/api-keys/{id}

GET    /admin/accounts
GET    /admin/permissions
GET    /admin/audit-logs

These endpoints require internal authentication and role-based authorization.

Endpoint naming may be adjusted to match the project's routing conventions.

13. Architectural Principles to Maintain

All future development should follow these principles:

1. Single Source of Truth

Laravel owns the database, business rules, permission model, and API key lifecycle.

2. Separation of Concerns

The React application handles customer-facing presentation and interaction. The Laravel backend handles security-sensitive business logic.

3. Least Privilege

Users and API keys should receive only the permissions they actually need.

4. Centralized Authorization

All permission checks must be performed by the backend.

5. Explicit Ownership

Every API key must have a clearly defined owner.

6. No Privilege Escalation

An API key must never grant permissions beyond the owner's current entitlements.

7. Auditable Administration

Internal actions involving customer accounts and API keys must be traceable.

8. Maintainability Over Duplication

Multiple interfaces are acceptable. Duplicated business logic is not.

14. Implementation Roadmap

Recommended implementation order:

Phase| Objective
1| Establish API key ownership and database structure
2| Implement centralized API key generation and lifecycle services
3| Implement subscription entitlements
4| Implement granular field-level authorization
5| Implement API authentication and permission middleware
6| Connect the React customer key management interface
7| Connect the internal administrative interface
😎 Implement audit logging
9| Implement stronger authentication and production security
10| Introduce organization-level Enterprise key management

Security-sensitive functionality should receive automated tests, especially for unauthorized field access, subscription changes, cross-account access, and key revocation.

15. Final Architectural Decision

The project will maintain two separate API key management interfaces:

- An internal administrative interface for developers, testers, and administrators.
- A customer-facing interface for individual and Enterprise customers.

However, both interfaces will use a single centralized API key management system implemented in Laravel.

The Laravel backend will remain the authoritative source of truth for:

- API key generation and validation.
- Key ownership.
- Permission assignment.
- Subscription entitlements.
- Field-level authorization.
- Key revocation and expiration.
- Administrative operations.
- Audit logging.

The two interfaces will have different access levels and responsibilities, but they must follow the same underlying business rules.

Final guiding principle:

Different interfaces. Different authority. One centralized system. One source of truth.

This architecture is intended to support the current development environment while allowing the application to evolve into a production-ready SaaS/API platform without requiring a fundamental redesign of its API key management system.