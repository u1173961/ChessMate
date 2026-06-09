# Symfony Upgrade Plan

Target destination: Symfony 7.4 LTS

Why 7.4 first:
- Current latest stable Symfony is 8.1, but 7.4 is the LTS line.
- This app is still on Symfony 3.4 with legacy bundles and project structure.
- Landing on 7.4 first keeps support strong while reducing the amount of change per hop.

Current starting point:
- Symfony 3.4 full-stack app using the legacy `app/` directory layout.
- PHP runtime in Docker is 7.2, while local CLI is already newer.
- Major blockers include FOSUserBundle, SwiftmailerBundle, AsseticBundle, and classic bundle inheritance.

Planned hops:

1. Preparatory app-code modernization on Symfony 3.4
- Fix brittle listener/service wiring.
- Move fragment controller references to FQCN syntax.
- Replace deprecated internal code paths where low-risk.
- Verify with syntax checks and existing tests where available.

2. Runtime modernization while staying on Symfony 3.4
- Move Docker to a supported PHP baseline for the intermediate upgrade path.
- Align Composer usage and local developer workflow.
- Confirm the app boots and fixtures still load.

3. Remove obsolete framework-era bundles on Symfony 3.4
- Remove DistributionBundle, GeneratorBundle, and WebServerBundle.
- Replace Assetic usage with direct asset delivery.
- Replace Swiftmailer integration with Symfony Mailer-compatible flow.

4. Replace FOSUserBundle
- Remove bundle inheritance from `CMUserBundle`.
- Keep the existing `User` entity, but migrate authentication to Symfony Security.
- Rebuild login, logout, registration, password reset, and profile edit flows.

5. Upgrade framework in supported hops
- Symfony 3.4 -> 4.4
- Symfony 4.4 -> 5.4
- Symfony 5.4 -> 6.4
- Symfony 6.4 -> 7.4

6. Modernize project structure
- Move toward `config/`, `public/`, and modern bundle registration.
- Replace old security config keys with authenticator-based security.
- Update test bootstrap and console/kernel wiring.

7. Optional final hop to Symfony 8.1
- Evaluate after the 7.4 upgrade is stable.
- Requires PHP 8.4.

Commit strategy:
- Make one commit per hop.
- Keep each hop bootable where practical.
- Prefer app-code modernization before dependency graph changes.
