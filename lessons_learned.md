## Logging and Auditing
- For all audit and action logging, use SQL tables (not CSV files) for reliability, queryability, and scalability.
# Lessons Learned

This document captures key lessons, recurring errors, and communication improvements identified during development. Update this file regularly to improve efficiency and avoid repeating mistakes.

## Communication & Workflow
- Clarify if you want a direct code change, a plan, or a comparison before work begins.
- Specify if you want a feature implemented immediately or just a review/roadmap.
- When asking for a fix, indicate if you want a best-practice solution or a minimal patch.
- If referencing a file or line, provide the filename and context (e.g., button, function, or section).
- Confirm after each major change if you want to proceed, test, or iterate further.

## Technical Lessons & Errors
- Always check for schema alignment between PHP, SQL, and documentation before debugging DB errors.
- Use proper link/button types for UI actions (e.g., mailto: for email, window.location for redirects).
- Validate that all quick-action buttons perform their intended function, not just alerts.
- When updating documentation, fix markdownlint errors and check for duplicate headings and broken links.
- After DB schema changes, verify with both code and database to ensure consistency.
- For new features, review top industry standards before implementation.

## Efficiency Improvements
- Use checklists or roadmaps for multi-step features.
- Summarize findings and next steps after each research or fetch operation.
- Store recurring issues and their solutions here for quick reference.

## Revenue Tracking for Contracts
- Separate recurring (monthly) and as-needed (regeneration) revenue in your schema.
- Store contract type, monthly recurring fee, and regeneration fee in the contracts table.
- Log each regeneration event in a separate table (e.g., contract_regenerations) with date and amount.
- For actual revenue, sum all logged regeneration events plus any monthly fees billed.
- For future projections, use active contracts’ monthly_fee and estimate regeneration revenue based on historical frequency.
- This enables accurate reporting, forecasting, and flexible analytics for both contract types.

---
Add new lessons below as they are discovered.
