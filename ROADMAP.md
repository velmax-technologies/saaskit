# SaaSKit Roadmap

SaaSKit is a Laravel-based SaaS REST API starter kit maintained by
Velmax Technologies. This roadmap defines the intended scope of the
public Free edition and future Pro and Enterprise editions.

## Product Principles

- Keep the Free edition functional and useful on its own.
- Prioritize security, maintainability, documentation, and ease of installation.
- Use automated tests and GitHub Actions to validate changes.
- Develop features on dedicated branches and merge through reviewed pull requests.
- Keep paid features separate from the public Free edition.
- Update this roadmap when milestones are completed or priorities change.

## Current Foundation

- [x] Laravel versioned REST API under `/api/v1`
- [x] Sanctum registration, login, logout, profile, and token management
- [x] Password-reset API and associated protections
- [x] Failed-login rate limiting
- [x] Organizations, memberships, and ownership transfer
- [x] Organization invitation workflows
- [x] Standard API responses and public resource identifiers
- [x] Docker Compose application services
- [x] Automated test suite and GitHub Actions CI

## Milestone 1 — Complete the Free API

Priority: High

- [ ] Review authentication, authorization, and security edge cases
- [ ] Implement email verification and its API workflow
- [ ] Review organization roles and permissions for consistency
- [ ] Verify tenant and organization data isolation across all relevant endpoints
- [ ] Review token creation, abilities, expiration policy, and revocation behavior
- [ ] Verify API validation, error responses, and rate-limit behavior
- [ ] Add regression tests for any identified gaps

Acceptance criteria:
- Required API workflows have automated tests.
- Users cannot access organization data without appropriate authorization.
- Authentication and authorization behavior is documented.
- CI passes on the feature branch before merge.

## Milestone 2 — Build the Free UI

Priority: High

- [ ] Add a basic Blade and Tailwind CSS interface
- [ ] Create login and registration pages
- [ ] Create forgot-password and reset-password pages
- [ ] Build a responsive authenticated dashboard
- [ ] Add user profile and account settings
- [ ] Add organization management screens
- [ ] Add organization member and invitation management screens
- [ ] Provide a consistent responsive layout and dark-theme support

Acceptance criteria:
- A developer can use the basic UI for the supported account and organization workflows.
- Forms provide validation feedback and useful error states.
- UI routes enforce authentication and authorization.
- Frontend assets build reproducibly.

## Milestone 3 — Developer Experience

Priority: High

- [ ] Improve first-time setup instructions
- [ ] Add useful Makefile commands for common development tasks
- [ ] Add and maintain the appropriate frontend dependency lockfile
- [ ] Document environment variables and configuration
- [ ] Document API endpoints and authentication examples
- [ ] Add troubleshooting guidance for Docker and database setup
- [ ] Validate installation from a clean clone
- [ ] Document backup and production deployment considerations

Acceptance criteria:
- A developer can follow the documented process from a clean clone.
- Required services start successfully.
- Database migrations and automated tests complete successfully.
- Dependency installation is reproducible and documented.

## Milestone 4 — Free Edition Release

Priority: High

- [ ] Review the Free feature scope and remove undocumented assumptions
- [ ] Verify the README against the actual application
- [ ] Publish a feature and requirements matrix
- [ ] Add a changelog and release checklist
- [ ] Run the complete test suite and CI
- [ ] Verify a fresh installation
- [ ] Tag the first stable Free release

Acceptance criteria:
- The public edition is independently useful.
- Installation, configuration, supported features, and limitations are documented.
- CI and release validation pass.
- The release has a version tag and changelog entry.

## Future Pro Edition

Planned; not part of the current Free milestone.

- [ ] Optional Passport authentication
- [ ] Optional JWT authentication
- [ ] Additional UI addons, including Livewire and Vue
- [ ] Flutter client/addon support
- [ ] PostgreSQL support
- [ ] Advanced roles and permissions
- [ ] Billing and subscription integrations
- [ ] Webhooks, API keys, and usage limits
- [ ] Additional developer productivity features

Paid features should be designed as maintainable extensions without making
the Free edition dependent on private code.

## Future Enterprise Edition

Planned; scope to be defined after the Free and Pro foundations mature.

- [ ] Enterprise SSO options, including SAML or LDAP where appropriate
- [ ] Advanced tenancy and isolation capabilities
- [ ] Audit and compliance features
- [ ] Enterprise deployment and operational guidance
- [ ] Advanced monitoring and recovery capabilities
- [ ] Enterprise support and customization options

## Working Process

1. Select one bounded roadmap item.
2. Inspect the current implementation before editing.
3. Create a dedicated feature branch from an up-to-date `master`.
4. Implement the smallest complete change with regression tests.
5. Run targeted tests, the full suite when practical, and `git diff --check`.
6. Review the diff and confirm the working tree state.
7. Push the branch and open a pull request.
8. Review CI and feedback; merge only with explicit approval.
9. Update this roadmap when the item is actually complete.

## Status Notes

Roadmap checkboxes represent implementation status, not merely planned work.
Revalidate them against the code before marking an item complete.
