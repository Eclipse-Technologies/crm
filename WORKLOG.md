# WORKLOG

Purpose: rolling implementation record for this project.
Update method: append newest entry at the top with date, scope, key changes, file touchpoints, and validation notes.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 109)

### Scope (Restore Action Live Parity)

- Add aria-live parity for restore button edge branches (unavailable and already-shown).

### Key Changes (Restore Action Live Parity)

- Added `announcePolicyManualRestoreAction(...)` helper in tasks.php.
- Wired restore button handler to emit aria-live feedback in no-op paths:
  - unavailable outside escalated mode,
  - already shown when no restore is needed.
- Kept existing key-status and toast behavior unchanged.

### Important Files (Restore Action Live Parity)

- tasks.php
- WORKLOG.md

### Validation (Restore Action Live Parity)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed live-region output for both branches:
  - unavailable: escalated mode required,
  - already shown: manual hint already shown.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 108)

### Scope (Restore No-Op Feedback Parity)

- Provide explicit toast feedback when the restore button is used while the manual hint is already visible.

### Key Changes (Restore No-Op Feedback Parity)

- Updated restore button handler in tasks.php.
- In the already-shown no-op branch:
  - preserved key status update,
  - added toast feedback: `Manual policy-copy hint is already shown.`

### Important Files (Restore No-Op Feedback Parity)

- tasks.php
- WORKLOG.md

### Validation (Restore No-Op Feedback Parity)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed no-op restore click emits both:
  - key status `Restore button -> Manual hint already shown`,
  - toast `Manual policy-copy hint is already shown.`

## 2026-06-05 - Nutshell Improvements (Task Module Slice 107)

### Scope (Dual-Path Restore Guidance Copy)

- Keep recovery guidance aligned with current UI by referencing both restore paths (keyboard and restore button).

### Key Changes (Dual-Path Restore Guidance Copy)

- Updated manual-hint hidden/dismissed toast copy in tasks.php to mention both:
  - `Shift+J`,
  - `Show manual hint` restore control.
- Updated `announcePolicyManualHintRestoreCue(...)` aria-live copy in tasks.php to the same dual-path guidance.
- Preserved existing cooldown and one-per-hidden-cycle announcement behavior.

### Important Files (Dual-Path Restore Guidance Copy)

- tasks.php
- WORKLOG.md

### Validation (Dual-Path Restore Guidance Copy)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed both toast and aria-live restore messages include:
  - `Shift+J`,
  - `Show manual hint`.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 106)

### Scope (Inline Manual-Hint Restore Control)

- Add an inline restore control for escalated hidden-hint state so recovery is available via UI, not only keyboard.

### Key Changes (Inline Manual-Hint Restore Control)

- Added `Show manual hint (Shift+J)` control (`.js-audit-policy-manual-restore`) in both audit-history render paths in tasks.php.
- Wired restore-control visibility to hidden escalated state in `refreshPolicyReadoutTone()`:
  - hidden + escalated: restore control shown,
  - otherwise: restore control hidden.
- Added restore-control click handler:
  - validates escalated mode,
  - restores manual hint when dismissed,
  - updates key status and toast,
  - emits shown-state aria-live message via existing announcer.

### Important Files (Inline Manual-Hint Restore Control)

- tasks.php
- WORKLOG.md

### Validation (Inline Manual-Hint Restore Control)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - restore control hidden before manual-hint hide,
  - restore control appears after hide,
  - activating restore control shows manual hint and hides restore control,
  - key status reports `Restore button -> Manual hint shown`.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 105)

### Scope (Restore-Cue Live Cooldown)

- Reduce rapid-toggle aria-live noise by adding a cooldown gate for the manual-hint restore cue announcement.

### Key Changes (Restore-Cue Live Cooldown)

- Added restore-cue live tracking state in tasks.php:
  - `lastPolicyManualHintRestoreCueLiveText`,
  - `lastPolicyManualHintRestoreCueLiveAt`.
- Updated `announcePolicyManualHintRestoreCue(...)` to suppress repeated identical restore-cue announcements inside `originFailureLiveCooldownMs`.
- Preserved existing one-per-hidden-cycle guard (`policyManualHintRestoreCueAnnounced`) and existing reset behavior when hint becomes visible or escalation clears.

### Important Files (Restore-Cue Live Cooldown)

- tasks.php
- WORKLOG.md

### Validation (Restore-Cue Live Cooldown)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - first hide emits restore cue,
  - rapid show->hide cycle suppresses repeated restore cue,
  - hide after cooldown emits restore cue again.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 104)

### Scope (Shown-State Live Symmetry)

- Make manual-hint live messaging symmetrical by adding hide-key guidance when the hint is announced as shown.

### Key Changes (Shown-State Live Symmetry)

- Updated `announcePolicyManualHintVisible(...)` aria-live text in tasks.php.
- Added explicit hide guidance to shown-state live message: `Press Shift+J to hide this hint.`
- Kept all existing cooldown, gating, and trigger-label behavior unchanged.

### Important Files (Shown-State Live Symmetry)

- tasks.php
- WORKLOG.md

### Validation (Shown-State Live Symmetry)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed shown-state live output contains both:
  - `press Ctrl+C` copy guidance,
  - `Press Shift+J to hide this hint` hide guidance.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 103)

### Scope (One-Time Live Restore Cue)

- Add an explicit aria-live restore reminder after manual hint hide/dismiss so keyboard recovery remains discoverable for screen-reader users.

### Key Changes (One-Time Live Restore Cue)

- Added `announcePolicyManualHintRestoreCue(...)` in tasks.php.
- Added hidden-state announcement flag (`policyManualHintRestoreCueAnnounced`) to avoid repeating the same cue during one hidden cycle.
- Reset cue flag when:
  - escalation clears,
  - manual hint becomes visible again.
- Hooked restore cue emission into both hide paths:
  - manual `Dismiss` button,
  - `Shift+J` hide toggle path.

### Important Files (One-Time Live Restore Cue)

- tasks.php
- WORKLOG.md

### Validation (One-Time Live Restore Cue)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - hide path live-region message includes `Press Shift+J to show it again`,
  - show->hide cycle re-emits the cue as expected,
  - dismiss path emits the same restore cue.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 102)

### Scope (Restore Cue on Manual-Hint Hide/Dismiss)

- Improve discoverability by adding an explicit keyboard restore cue when users hide or dismiss the escalated manual-copy hint.

### Key Changes (Restore Cue on Manual-Hint Hide/Dismiss)

- Updated manual hint dismiss toast text to include restore guidance: `Press Shift+J to show again.`
- Updated Shift+J hide-state toast text to include the same restore guidance for consistency.
- Kept current toggle behavior, escalation gating, and aria-live announcements unchanged.

### Important Files (Restore Cue on Manual-Hint Hide/Dismiss)

- tasks.php
- WORKLOG.md

### Validation (Restore Cue on Manual-Hint Hide/Dismiss)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - dismiss action toast includes `Press Shift+J to show again`,
  - Shift+J hide action toast includes `Press Shift+J to show again`.

## 2026-06-05 - Nutshell Improvements (Task Module Slice 101)

### Scope (Shift+J Manual Hint Toggle)

- Add a keyboard path to toggle manual fallback hint visibility while in escalated policy-copy mode.

### Key Changes (Shift+J Manual Hint Toggle)

- Added `Shift+J` shortcut handling in tasks.php.
- Added `toggleManualPolicyHint(...)` helper with scoped behavior:
  - outside escalated mode: no-op with clear status/toast guidance,
  - inside escalated mode: toggles manual hint hidden/shown for the current panel session.
- Updated shortcut discoverability surfaces for parity:
  - cheatline,
  - compact hint text,
  - detailed help text,
  - keyboard source tooltip shortcut list.
- Reused existing live announcers for shown/dismissed hint feedback.

### Important Files (Shift+J Manual Hint Toggle)

- tasks.php
- WORKLOG.md

### Validation (Shift+J Manual Hint Toggle)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - hint text includes Shift+J,
  - Shift+J outside escalated mode reports unavailable guidance,
  - in escalated mode, Shift+J hides and re-shows the manual hint with expected key status updates.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 100)

### Scope (Session-Local Manual Hint Dismiss)

- Add a non-destructive, per-panel-session dismiss control for the escalated manual-copy fallback hint.

### Key Changes (Session-Local Manual Hint Dismiss)

- Added `Dismiss` control inside `.js-audit-policy-manual-hint` in both render paths in tasks.php.
- Added session-local dismissed state (`policyManualHintDismissed`) in panel wiring.
- Updated manual-hint visibility logic:
  - when escalated unavailable mode is active, show hint only if not dismissed,
  - keep hint dismissed for subsequent failures in the same panel session,
  - reset dismissed flag when escalation state clears.
- Added dismiss action feedback:
  - key status update,
  - toast confirmation,
  - aria-live dismissal announcement (`announcePolicyManualHintDismissed(...)`).

### Important Files (Session-Local Manual Hint Dismiss)

- tasks.php
- WORKLOG.md

### Validation (Session-Local Manual Hint Dismiss)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - escalated mode shows manual hint and dismiss control,
  - clicking dismiss hides hint,
  - subsequent failures in the same panel session keep hint hidden,
  - dismissal key status, toast, and aria-live message are emitted.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 99)

### Scope (Explicit Manual Copy Keystroke)

- Improve escalated fallback clarity by explicitly instructing users to press Ctrl+C after selecting policy text.

### Key Changes (Explicit Manual Copy Keystroke)

- Updated inline manual hint text in both render paths in tasks.php:
  - from generic manual-copy wording to explicit “select policy line, then press Ctrl+C”.
- Updated escalated manual-hint aria-live message to include Ctrl+C instruction for screen-reader parity.
- Preserved all escalation gating and visibility behavior from prior slice.

### Important Files (Explicit Manual Copy Keystroke)

- tasks.php
- WORKLOG.md

### Validation (Explicit Manual Copy Keystroke)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - escalated inline manual hint text contains Ctrl+C instruction,
  - escalated aria-live manual-hint announcement contains Ctrl+C instruction.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 98)

### Scope (Manual-Hint Visibility Live Cue)

- Add cooldowned screen-reader feedback when inline manual-copy hint becomes visible in escalated Shift+K failure mode.

### Key Changes (Manual-Hint Visibility Live Cue)

- Added manual-hint live-announcement state tracking in tasks.php.
- Added `announcePolicyManualHintVisible(...)` for explicit manual-copy guidance in aria-live output.
- Updated escalated policy-copy failure flow to emit manual-hint visibility announcement after failure announcement, ensuring the transition cue is the final live-region message users hear.
- Kept manual hint visibility strictly tied to escalated unavailable mode.

### Important Files (Manual-Hint Visibility Live Cue)

- tasks.php
- WORKLOG.md

### Validation (Manual-Hint Visibility Live Cue)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - first forced Shift+K failure announces retry wording,
  - second forced Shift+K failure shows manual hint and announces manual-hint visibility guidance in aria-live,
  - manual hint display is active in escalated state.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 97)

### Scope (Inline Manual-Selection Hint)

- Add an inline fallback hint beneath policy readout that appears only when policy-copy failures escalate to unavailable-context mode.

### Key Changes (Inline Manual-Selection Hint)

- Added `.js-audit-policy-manual-hint` under policy readout in both render paths in tasks.php.
- Wired hint node in `wireAuditHistoryFilters`.
- Updated `refreshPolicyReadoutTone()` to toggle hint visibility only when escalated unavailable mode is active (`policyCopyFailureStreak >= 2` with failed state).
- Preserved existing default and first-failure visuals with no extra hint noise.

### Important Files (Inline Manual-Selection Hint)

- tasks.php
- WORKLOG.md

### Validation (Inline Manual-Selection Hint)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - hint hidden by default,
  - still hidden after first forced Shift+K failure,
  - visible after second forced Shift+K failure with expected manual-selection copy guidance text.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 96)

### Scope (Policy Copy Failure Live Escalation)

- Align screen-reader feedback with repeated-failure unavailable-context UX by escalating Shift+K policy-copy aria-live wording after consecutive failures.

### Key Changes (Policy Copy Failure Live Escalation)

- Updated `announcePolicyCopyFailure(...)` in tasks.php to accept an escalation flag.
- Added escalated live wording for repeated policy-copy failures:
  - indicates policy copy may be unavailable in the current browser context,
  - suggests manual selection of policy text.
- Updated policy-copy failure call sites to pass escalation state derived from failure streak (`policyCopyFailureStreak >= 2`).
- Preserved cooldown/dedup behavior through existing failure-live timing policy.

### Important Files (Policy Copy Failure Live Escalation)

- tasks.php
- WORKLOG.md

### Validation (Policy Copy Failure Live Escalation)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - first forced Shift+K failure announces retry wording,
  - second forced Shift+K failure announces unavailable-context manual-selection wording.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 95)

### Scope (Repeated Failure Unavailable Hint)

- Improve Shift+K failure guidance by escalating from generic retry messaging to an explicit browser-context unavailable hint after consecutive copy failures.

### Key Changes (Repeated Failure Unavailable Hint)

- Added policy copy failure streak tracking in tasks.php (`policyCopyFailureStreak`).
- Added policy copy state helpers:
  - `markPolicyCopyFailure()`
  - `markPolicyCopySuccess()`
- Updated `refreshPolicyReadoutTone()` to switch to stronger unavailable-context styling and title after repeated failures.
- Updated Shift+K copy messaging:
  - first failure: standard failure/retry guidance,
  - repeated failures: explicit clipboard/browser-context unavailable hints in toast and readout title.

### Important Files (Repeated Failure Unavailable Hint)

- tasks.php
- WORKLOG.md

### Validation (Repeated Failure Unavailable Hint)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - first forced Shift+K failure shows retry title,
  - second forced Shift+K failure escalates to unavailable-context title and warning tone,
  - second failure toast includes browser-context clipboard hint.

### Validation Note

- Because the key handler is bound to the shell element, shell focus was re-applied between forced failure attempts during browser validation to ensure both Shift+K events were handled by the panel.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 94)

### Scope (Policy Copy Retry Hint Tone)

- Add a compact visual retry cue on policy readout when policy copy fails, improving feedback clarity for `Shift+K` copy failures.

### Key Changes (Policy Copy Retry Hint Tone)

- Added policy-copy failure state tracking in `tasks.php` (`lastPolicyCopyFailed`).
- Added `refreshPolicyReadoutTone()` and wired it into readout refresh.
- On policy-copy failure/unavailable payload, readout now switches to warning tone and retry title hint:
  - warm warning color/background,
  - hint title: retry with Shift+K.
- On policy-copy success path, warning tone resets to normal readout styling.

### Important Files (Policy Copy Retry Hint Tone)

- tasks.php
- WORKLOG.md

### Validation (Policy Copy Retry Hint Tone)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - forced policy-copy failure applies warning tone and retry title hint.
- Environment note:
  - clipboard writes were denied in this browser context during this slice, so success-path visual reset was verified via code path review (explicit reset on successful copy branch).

## 2026-06-04 - Nutshell Improvements (Task Module Slice 93)

### Scope (Policy Copy Aria-Live Parity)

- Add accessibility parity for `Shift+K` policy copy by emitting explicit aria-live feedback for success/failure paths.

### Key Changes (Policy Copy Aria-Live Parity)

- Added policy-copy live announcement state tracking in `tasks.php`:
  - success message + cooldown timestamp,
  - failure message + cooldown timestamp.
- Added live-region announcers:
  - `announcePolicyCopy(...)`
  - `announcePolicyCopyFailure(...)`
- Updated `copyPolicyReadout(...)` to emit live-region announcements for:
  - success,
  - failed clipboard write,
  - unavailable policy-readout payload.
- Reused existing policy cooldown values and hint live debounce behavior for consistent announcement cadence.

### Important Files (Policy Copy Aria-Live Parity)

- tasks.php
- WORKLOG.md

### Validation (Policy Copy Aria-Live Parity)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - `Shift+K` success updates live region with policy-copy announcement,
  - key status and toast remain correct,
  - copied payload remains intact.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 92)

### Scope (Policy Readout Copy Shortcut)

- Add one-key policy export from the audit panel so current tuning state can be copied quickly for handoff/debug notes.

### Key Changes (Policy Readout Copy Shortcut)

- Added `Shift+K` shortcut in `tasks.php` to copy the current policy readout text.
- Added `copyPolicyReadout(...)` helper that copies the visible readout (fallbacks to generated readout text when needed).
- Added `policy` badge style in shortcut copy badge feedback.
- Updated shortcut surfaces in both render paths for parity:
  - cheatline,
  - compact hint,
  - detailed shortcut help,
  - keyboard source tooltip shortcut list.

### Important Files (Policy Readout Copy Shortcut)

- tasks.php
- WORKLOG.md

### Validation (Policy Readout Copy Shortcut)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - help and hint include `Shift+K`,
  - `Shift+K` copies the exact current policy readout text,
  - key status, toast, and copy badge feedback all reflect policy-copy action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 91)

### Scope (Invalid Policy Attribute Fallback Indicator)

- Make policy tuning safer by surfacing when shell data attributes are invalid and defaults were used as fallback.

### Key Changes (Invalid Policy Attribute Fallback Indicator)

- Extended policy loader parsing in `tasks.php` to return metadata per attribute (value + fallback-used flag + field label).
- Added fallback tracking list for the active shell policy load.
- Updated policy readout to append compact fallback detail when invalid attributes are detected:
  - `Fallback: Toast, Success, ...`
- Updated `Shift+P` reload toast to include fallback-applied note when defaults were substituted for invalid attributes.
- Preserved existing behavior for valid numeric values and blank/missing values.

### Important Files (Invalid Policy Attribute Fallback Indicator)

- tasks.php
- WORKLOG.md

### Validation (Invalid Policy Attribute Fallback Indicator)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - with invalid attribute values, readout shows fallback marker and field list,
  - fallbacked fields use default values while valid fields retain provided values,
  - Shift+P status and toast include fallback-applied feedback.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 90)

### Scope (In-Help Policy Readout)

- Surface current copy-announcement policy values directly in shortcut help to make runtime tuning state visible without inspecting attributes.

### Key Changes (In-Help Policy Readout)

- Added `.js-audit-policy-readout` mini readout to shortcut help in both render paths in `tasks.php`.
- Added policy readout sync helpers in `wireAuditHistoryFilters`:
  - `policyReadoutText(policy)`
  - `refreshPolicyReadout(policy)`
- Wired `loadCopyAnnouncementPolicy()` to refresh the readout whenever policy is loaded/reloaded.
- `Shift+P` now updates both runtime policy behavior and visible readout values in-place.

### Important Files (In-Help Policy Readout)

- tasks.php
- WORKLOG.md

### Validation (In-Help Policy Readout)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - initial help readout shows default values,
  - after changing shell policy attributes and pressing Shift+P, readout updates to new values,
  - key status and reload toast remain correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 89)

### Scope (Runtime Policy Reload Shortcut)

- Add an in-panel keyboard path to reload copy-announcement policy values from shell data attributes without closing/reopening the audit panel.

### Key Changes (Runtime Policy Reload Shortcut)

- Added `Shift+P` shortcut in `tasks.php` audit shell key handling to reload policy timing values from shell attributes at runtime.
- Refactored policy setup into `loadCopyAnnouncementPolicy()` with mutable `copyAnnouncementPolicy` state so reloading updates active cooldown/recovery behavior immediately.
- Added toast feedback and key-status update for `Shift+P` reload action.
- Updated shortcut surfaces in both PHP and JS render paths for parity:
  - cheatline,
  - compact shortcut hint,
  - detailed shortcut help text,
  - keyboard source tooltip list.

### Important Files (Runtime Policy Reload Shortcut)

- tasks.php
- WORKLOG.md

### Validation (Runtime Policy Reload Shortcut)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - `Shift+P` updates key status to policy reload,
  - reload toast appears with timing values,
  - after setting `data-origin-success-live-cooldown-ms="0"` and pressing `Shift+P`, rapid repeated `O` triggers multiple live-region mutations (dedup disabled as expected).

## 2026-06-04 - Nutshell Improvements (Task Module Slice 88)

### Scope (Data-Attribute Policy Tuning)

- Make copy-announcement timing policy externally tunable per audit shell via data attributes while preserving current defaults.

### Key Changes (Data-Attribute Policy Tuning)

- Added default policy data attributes to audit shell markup in both render paths in tasks.php:
  - data-origin-toast-cooldown-ms
  - data-origin-success-live-cooldown-ms
  - data-origin-failure-live-cooldown-ms
  - data-origin-recovery-window-ms
- Updated JS wiring to read policy values from shell attributes with fallback defaults:
  - introduced readPolicyMs(...) guard parser,
  - kept existing policy behavior as fallback when attributes are missing/invalid.
- No changes to visible copy semantics by default values.

### Important Files (Data-Attribute Policy Tuning)

- tasks.php
- WORKLOG.md

### Validation (Data-Attribute Policy Tuning)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - shell exposes default policy data attributes,
  - origin copy live/status behavior remains correct with default policy values.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 87)

### Scope (Policy Intent Comment)

- Improve maintainability by adding an explicit code note explaining why copy-feedback timings are centralized.

### Key Changes (Policy Intent Comment)

- Added a concise comment above `copyAnnouncementPolicy` in `tasks.php` clarifying the intent:
  - keep copy-feedback timing values in one location for easier UX tuning and reduced handler churn.
- No logic changes.

### Important Files (Policy Intent Comment)

- tasks.php
- WORKLOG.md

### Validation (Policy Intent Comment)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime sanity check on `tasks.php` confirmed origin copy status/live announcements remain unchanged.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 86)

### Scope (Copy Announcement Policy Consolidation)

- Improve maintainability by centralizing origin-copy announcement cooldown/recovery timing constants into a single policy block.

### Key Changes (Copy Announcement Policy Consolidation)

- Added `copyAnnouncementPolicy` object in tasks.php with unified timing values for:
  - origin toast cooldown
  - success live cooldown
  - failure live cooldown
  - recovery window
- Rewired announcement/cooldown/recovery checks to read from the policy object.
- Removed scattered standalone timing constants in favor of policy-based access.
- Preserved all runtime behavior and messaging semantics.

### Important Files (Copy Announcement Policy Consolidation)

- tasks.php
- WORKLOG.md

### Validation (Copy Announcement Policy Consolidation)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - success live dedup still yields single mutation on rapid repeated O,
  - fail-then-success flow still announces failure then recovered success.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 85)

### Scope (Recovered Success Announcement)

- Add an explicit accessibility recovery signal when an origin-copy success follows a recent failure.

### Key Changes (Recovered Success Announcement)

- Added failure-recovery tracking in tasks.php panel logic:
  - lastOriginCopyFailureAt
  - originRecoveryWindowMs
- Updated success flows to detect recent failure and emit recovered live message:
  - copyCurrentOriginLabel(...)
  - copyOriginContextSnapshot(...)
- Updated announceOriginCopy(...) to support recovered-mode messaging:
  - Origin copy recovered: ...
- Reset recovery marker after successful copy to avoid repeated false recovery announcements.

### Important Files (Recovered Success Announcement)

- tasks.php
- WORKLOG.md

### Validation (Recovered Success Announcement)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php (forced failure then success) confirmed:
  - failure live message appears,
  - next success within recovery window announces Origin copy recovered: ...,
  - key status remains correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 84)

### Scope (Success Aria-Live Cooldown)

- Reduce repetitive screen-reader chatter by deduping rapid repeated success announcements for origin copy actions.

### Key Changes (Success Aria-Live Cooldown)

- Added success-live dedup state in tasks.php panel logic:
  - lastOriginSuccessLiveText
  - lastOriginSuccessLiveAt
  - originSuccessLiveCooldownMs
- Updated announceOriginCopy(copyLabel, triggerLabel) to suppress duplicate rapid success messages within a short cooldown window.
- Kept success toasts, key status, and visual copy feedback unchanged.

### Important Files (Success Aria-Live Cooldown)

- tasks.php
- WORKLOG.md

### Validation (Success Aria-Live Cooldown)

- php -l tasks.php passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php (rapid repeated O) confirmed:
  - live region mutationCount: 1 for duplicate rapid success announcements,
  - success message remained correct,
  - key status remained correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 83)

### Scope (Failure Live Recovery Hint)

- Add a concise recovery hint to origin-copy failure aria-live announcements so assistive-tech users get immediate next-step guidance.

### Key Changes (Failure Live Recovery Hint)

- Updated `announceOriginCopyFailure(triggerLabel)` in `tasks.php` to append:
  - `Try again or use Origin chip.`
- Applied uniformly across both failure triggers that already route through this helper:
  - `O` origin copy failure
  - `Shift+O` origin-context copy failure
- Kept failure cooldown/dedup behavior unchanged.

### Important Files (Failure Live Recovery Hint)

- tasks.php
- WORKLOG.md

### Validation (Failure Live Recovery Hint)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` (forced clipboard failure) confirmed live-region messages now include recovery hint for both `O` and `Shift+O` failure paths.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 82)

### Scope (Failure Aria-Live Cooldown)

- Prevent rapid repeated origin-copy failures from spamming duplicate aria-live announcements.

### Key Changes (Failure Aria-Live Cooldown)

- Added failure-live dedup state in `tasks.php` panel logic:
  - `lastOriginFailureLiveText`
  - `lastOriginFailureLiveAt`
  - `originFailureLiveCooldownMs`
- Updated `announceOriginCopyFailure(triggerLabel)` to suppress duplicate failure messages within a short cooldown window.
- Kept failure toasts and key-status behavior unchanged.

### Important Files (Failure Aria-Live Cooldown)

- tasks.php
- WORKLOG.md

### Validation (Failure Aria-Live Cooldown)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` (forced clipboard failure + rapid repeated `O`) confirmed:
  - live region updated once (`mutationCount: 1`) for duplicate rapid failures,
  - failure message remained correct,
  - key status remained correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 81)

### Scope (Origin Copy Failure Aria-Live Parity)

- Add explicit live-region failure announcements for origin-copy flows so accessibility feedback parity matches success paths.

### Key Changes (Origin Copy Failure Aria-Live Parity)

- Added `announceOriginCopyFailure(triggerLabel)` in `tasks.php` using existing live-region debounce channel.
- Wired failure branches to announce copy failures:
  - `copyCurrentOriginLabel(...)`
  - `copyOriginContextSnapshot(...)`
- Kept existing visual failure toasts and key-status updates unchanged.

### Important Files (Origin Copy Failure Aria-Live Parity)

- tasks.php
- WORKLOG.md

### Validation (Origin Copy Failure Aria-Live Parity)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` (forced clipboard failure) confirmed:
  - live region announces `Origin copy failed. Trigger: O -> Copy origin.`,
  - live region announces `Origin copy failed. Trigger: Shift+O -> Copy origin context.`,
  - key status reflects failure for both actions.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 80)

### Scope (Origin Toast Cooldown)

- Reduce toast noise during rapid repeated origin-copy actions by coalescing duplicate success toasts within a short cooldown window.

### Key Changes (Origin Toast Cooldown)

- Added origin-toast dedup state in `tasks.php` panel logic:
  - `lastOriginToastText`
  - `lastOriginToastAt`
  - `originToastCooldownMs`
- Added `showOriginCopyToast(messageText)` helper:
  - suppresses duplicate origin-copy success toasts if repeated too quickly,
  - leaves normal toasts unchanged outside cooldown.
- Routed origin-copy success paths through the helper:
  - `copyCurrentOriginLabel(...)`
  - `copyOriginContextSnapshot(...)`
- Kept key-status, badge, pulse, and aria-live announcements unchanged.

### Important Files (Origin Toast Cooldown)

- tasks.php
- WORKLOG.md

### Validation (Origin Toast Cooldown)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - rapid repeated `O` does not keep extending duplicate toast visibility,
  - origin copy key status remains correct,
  - toast lifecycle still fades/clears as expected.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 79)

### Scope (Origin Copy Aria-Live Announcements)

- Add explicit screen-reader live announcements for origin-copy actions to improve non-visual feedback precision.

### Key Changes (Origin Copy Aria-Live Announcements)

- Added `announceOriginCopy(copyLabel, triggerLabel)` helper in `tasks.php` using existing live region and debounce path.
- Wired origin-copy success flows to announce operation-specific live messages:
  - `copyCurrentOriginLabel(...)` announces origin copy with trigger context,
  - `copyOriginContextSnapshot(...)` announces origin-context copy with trigger context.
- Kept visual toasts/badges/key-status behavior unchanged.
- Minor consistency update in context-copy handler to reuse a stable `originLabel` per copy action.

### Important Files (Origin Copy Aria-Live Announcements)

- tasks.php
- WORKLOG.md

### Validation (Origin Copy Aria-Live Announcements)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `O` updates live region with `Origin copy: <label>. Trigger: O -> Copy origin.`,
  - `Shift+O` updates live region with `Origin copy: <label> context. Trigger: Shift+O -> Copy origin context.`,
  - key status remains correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 78)

### Scope (Reduced-Motion Shift+O Text Swap Parity)

- Extend reduced-motion micro-confirmation so `Shift+O` has distinct context-copy text feedback on the Origin chip.

### Key Changes (Reduced-Motion Shift+O Text Swap Parity)

- Updated `pulseOriginChip(...)` in `tasks.php` to accept a custom confirmation label.
- Reduced-motion chip text now uses operation-specific labels:
  - origin copy (`O` / chip click): `Origin: Copied`
  - origin-context copy (`Shift+O`): `Origin: Context Copied`
- Updated call sites:
  - `copyCurrentOriginLabel(...)` passes `Copied`
  - `copyOriginContextSnapshot(...)` passes `Context Copied`
- Kept reset behavior and default-motion branch unchanged.

### Important Files (Reduced-Motion Shift+O Text Swap Parity)

- tasks.php
- WORKLOG.md

### Validation (Reduced-Motion Shift+O Text Swap Parity)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed (reduced-motion):
  - `O`: `Origin: Copied` then reset,
  - `Shift+O`: `Origin: Context Copied` then reset,
  - `Shift+O` toast/key-status/badge remain correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 77)

### Scope (Reduced-Motion Origin Text Swap)

- Add an explicit non-motion micro-confirmation for reduced-motion users: brief Origin chip text swap to `Origin: Copied` after successful origin copy.

### Key Changes (Reduced-Motion Origin Text Swap)

- Updated `pulseOriginChip()` in `tasks.php`:
  - reduced-motion branch now sets chip text to `Origin: Copied` briefly,
  - then restores canonical origin label.
- Added reset timer guard for text swap (`originChipTextResetTimer`) to prevent overlap artifacts during rapid copy actions.
- Added/maintained origin label cache on chip via `data-origin-label` during source updates:
  - `applySourceIndicator(...)`
  - `refreshShellVisualState(...)`
- Kept default-motion branch unchanged (no temporary text swap).

### Important Files (Reduced-Motion Origin Text Swap)

- tasks.php
- WORKLOG.md

### Validation (Reduced-Motion Origin Text Swap)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - reduced-motion: `Origin: Default` -> `Origin: Copied` -> `Origin: Default`,
  - default motion: no temporary text swap,
  - key status and origin-copy toast remain correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 76)

### Scope (Origin Context Help Note)

- Add a compact shortcut-help legend that explicitly defines origin context payload composition.

### Key Changes (Origin Context Help Note)

- Updated shortcut help text in both render paths in [tasks.php](tasks.php):
  - PHP renderer `renderTaskAuditHistoryHtml(...)`
  - JS renderer `renderAuditHistory(...)`
- Added note to help block:
  - `Ctx note: Origin Context = Origin + Source + Filter.`
- Kept all existing shortcut mappings/behavior unchanged.

### Important Files (Origin Context Help Note)

- tasks.php
- WORKLOG.md

### Validation (Origin Context Help Note)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - pressing `?` shows shortcut help,
  - help text contains the new Ctx note,
  - key status remains accurate.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 75)

### Scope (Reduced-Motion Origin Pulse Fallback)

- Respect reduced-motion user preference for Origin chip copy confirmation while keeping clear success feedback.

### Key Changes (Reduced-Motion Origin Pulse Fallback)

- Updated `pulseOriginChip()` in `tasks.php` to detect:
  - `prefers-reduced-motion: reduce`
- Behavior now branches by motion preference:
  - default motion: short scale pulse + ring,
  - reduced motion: no scale animation, subtle accent (ring/background) with quick reset.
- Added transition timing adjustments and reset cleanup for the reduced-motion branch.
- Kept copy semantics unchanged for `O`, chip click, and `Shift+O` flows.

### Important Files (Reduced-Motion Origin Pulse Fallback)

- tasks.php
- WORKLOG.md

### Validation (Reduced-Motion Origin Pulse Fallback)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - default motion branch applies scale pulse,
  - reduced-motion branch keeps transform at identity (no scale),
  - reduced-motion branch still shows subtle visual confirmation,
  - key status/toast remain correct for origin copy.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 74)

### Scope (Origin Chip Pulse Confirmation)

- Add short visual pulse on the Origin chip after successful origin copy actions to provide immediate in-panel confirmation.

### Key Changes (Origin Chip Pulse Confirmation)

- Added `pulseOriginChip()` in `tasks.php` audit-panel logic:
  - temporary scale-up pulse,
  - brief accent ring,
  - automatic reset.
- Added pulse timer guard (`originChipPulseResetTimer`) to avoid overlapping pulse artifacts.
- Wired pulse into successful copy paths:
  - `copyCurrentOriginLabel(...)` (`O` and chip-click flow),
  - `copyOriginContextSnapshot(...)` (`Shift+O` flow).
- Added `copyOriginContextSnapshot(statusPrefix)` helper so `Shift+O` uses a dedicated context-copy success/failure path while preserving existing toast/badge semantics.

### Important Files (Origin Chip Pulse Confirmation)

- tasks.php
- WORKLOG.md

### Validation (Origin Chip Pulse Confirmation)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `O` triggers Origin chip pulse and reset,
  - `Shift+O` triggers Origin chip pulse,
  - `Shift+O` key status/toast/badge remain correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 73)

### Scope (Adaptive Origin Context Badge Label)

- Improve `Shift+O` badge readability by auto-expanding the origin-context badge label on wider audit panels.

### Key Changes (Adaptive Origin Context Badge Label)

- Updated `flashShortcutCopyBadge(mode)` in `tasks.php`:
  - detects current audit panel width,
  - uses compact label `OriginCtx` on narrower shells,
  - uses expanded label `Origin Context` on wider shells.
- Kept badge color treatment and existing copy payload/toast behavior unchanged.

### Important Files (Adaptive Origin Context Badge Label)

- tasks.php
- WORKLOG.md

### Validation (Adaptive Origin Context Badge Label)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - narrow shell (`~420px`) shows badge text `OriginCtx`,
  - wide shell (`~760px`) shows badge text `Origin Context`,
  - `Shift+O` origin-context toast remains correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 72)

### Scope (Shift+O Origin Context Copy)

- Add `Shift+O` shortcut to copy compact origin context payload (`Origin`, `Source`, `Filter`) with consistent badge and toast feedback.

### Key Changes (Shift+O Origin Context Copy)

- Added `buildOriginContextSnapshot()` in `tasks.php` to build:
  - `Origin: ... | Source: ... | Filter: ...`
- Added `originContextPreviewToastText(snapshotText)` with preview + `(<n> chars)` suffix.
- Added `Shift+O` key handler in audit shell keydown flow:
  - copies origin context payload,
  - updates key status,
  - shows preview toast,
  - triggers new `OriginCtx` badge variant.
- Extended `flashShortcutCopyBadge(mode)` with `origin-context` mode.
- Updated guidance text in both render paths:
  - cheatline includes `Shift+O`,
  - compact/detailed hint strings include `Shift+O`,
  - shortcut help includes `Shift+O = Copy origin/source/filter context`.
- Updated keyboard-source tooltip shortcut list to include `Shift+O`.

### Important Files (Shift+O Origin Context Copy)

- tasks.php
- WORKLOG.md

### Validation (Shift+O Origin Context Copy)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `Shift+O` toast shows origin-context preview + char count,
  - key status updates for `Shift+O` copy action,
  - badge shows `OriginCtx`,
  - cheatline/help text include `Shift+O` guidance.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 71)

### Scope (Keyboard Origin Copy Shortcut)

- Add keyboard shortcut `O` to copy current Origin label, matching Origin chip click behavior and copy feedback conventions.

### Key Changes (Keyboard Origin Copy Shortcut)

- Added reusable `copyCurrentOriginLabel(statusPrefix)` helper in `tasks.php` panel logic.
- Wired `O` key handler in audit shell keydown flow:
  - copies current origin label,
  - updates key status,
  - shows origin copy toast,
  - triggers `Origin` badge variant.
- Refactored Origin chip click handler to reuse `copyCurrentOriginLabel(...)` for parity.
- Updated shortcut guidance text in both render paths:
  - cheatline includes `O`,
  - compact/detailed hint strings include `O`,
  - shortcut help explains `O = Copy current origin label`.
- Updated keyboard source tooltip shortcut list to include `O`.

### Important Files (Keyboard Origin Copy Shortcut)

- tasks.php
- WORKLOG.md

### Validation (Keyboard Origin Copy Shortcut)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - pressing `O` copies origin and shows `Origin copied: <label>.`,
  - key status updates to `O -> Copy origin -> <label>`,
  - copy badge shows `Origin`,
  - cheatline/help text include origin shortcut guidance.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 70)

### Scope (Origin Chip Copy Action)

- Make the Origin chip interactive so users can copy current origin (`Row`/`Global`/`Default`) directly from the audit panel.

### Key Changes (Origin Chip Copy Action)

- Converted Origin chip element to a clickable button in both render paths in `tasks.php`:
  - PHP `renderTaskAuditHistoryHtml(...)`
  - JS `renderAuditHistory(...)`
- Added `currentOriginLabel()` helper in panel wiring to derive origin from chip/source text safely.
- Added Origin chip click handler:
  - copies origin label to clipboard,
  - updates key status line,
  - shows `Origin copied: ...` toast,
  - triggers new `Origin` shortcut badge variant.
- Extended `flashShortcutCopyBadge(mode)` with `origin` mode styling.
- Kept Origin chip title/ARIA label synchronized with live source updates in `applySourceIndicator(...)`.

### Important Files (Origin Chip Copy Action)

- tasks.php
- WORKLOG.md

### Validation (Origin Chip Copy Action)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - Origin chip is clickable,
  - clicking chip shows toast `Origin copied: <label>.`,
  - key status updates to `Origin chip copy -> <label>`,
  - copy badge shows `Origin` variant.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 69)

### Scope (Origin Chip Visibility)

- Add a dedicated, always-visible Origin chip beside the source indicator so users can quickly confirm whether filter state comes from Row, Global, or Default.

### Key Changes (Origin Chip Visibility)

- Added a new `js-audit-origin-chip` element in both render paths in `tasks.php`:
  - PHP `renderTaskAuditHistoryHtml(...)`
  - JS `renderAuditHistory(...)`
- Updated source-state refresh logic to keep the new chip synchronized with source updates:
  - `applySourceIndicator(source, filter)` now updates source text and Origin chip text/style.
  - `refreshShellVisualState(targetShell, selectedFilter, source)` now mirrors Origin chip state during bulk/restore flows.
- Added source-specific chip styling accents:
  - Row: teal tint
  - Global: blue tint
  - Default: neutral slate tint

### Important Files (Origin Chip Visibility)

- tasks.php
- WORKLOG.md

### Validation (Origin Chip Visibility)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - Origin chip renders and reflects current source,
  - keyboard state transitions update both source text and Origin chip (e.g., `A` keeps `Row`, `R` returns to `Default` when global is off),
  - Origin chip color accents change with source state.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 68)

### Scope (Ctrl+Y Toast Length Suffix)

- Add payload-length suffix to Ctrl+Y source/filter/origin toast preview for consistency with Shift+Y snapshot feedback.

### Key Changes (Ctrl+Y Toast Length Suffix)

- Added `sourceFilterPreviewToastText(snapshotText)` in tasks.php client logic.
- Implemented concise preview + length format for Ctrl+Y success toast:
  - `Source/filter copied: <preview> (<n> chars)`
- Wired Ctrl+Y copy handler to use the new toast formatter while keeping copied payload unchanged.

### Important Files (Ctrl+Y Toast Length Suffix)

- tasks.php
- WORKLOG.md

### Validation (Ctrl+Y Toast Length Suffix)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in tasks.php.
- Runtime verification on tasks.php confirmed:
  - Ctrl+Y toast includes source/filter/origin preview plus `(<n> chars)` suffix,
  - key status remains accurate for Ctrl+Y action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 67)

### Scope (Ctrl+Y Origin Context)

- Include current source-indicator origin (`Row`/`Global`/`Default`) in Ctrl+Y copied payload.

### Key Changes (Ctrl+Y Origin Context)

- Updated `buildSourceFilterSnapshot()` in `tasks.php` to parse the panel source indicator and derive current origin label.
- Extended Ctrl+Y payload format to include origin:
  - `Source: ... | Filter: ... | Origin: ...`
- Updated shortcut help text in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to describe expanded Ctrl+Y context copy behavior.

### Important Files (Ctrl+Y Origin Context)

- tasks.php
- WORKLOG.md

### Validation (Ctrl+Y Origin Context)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - Ctrl+Y toast now includes `Origin: <Row|Global|Default>`,
  - key status remains accurate for Ctrl+Y action,
  - shortcut help text reflects source/filter/origin copy scope.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 66)

### Scope (Ctrl+Y Filter Badge Variant)

- Add a distinct badge variant for `Ctrl+Y` copy feedback so source+filter copy is visually different from snapshot copy.

### Key Changes (Ctrl+Y Filter Badge Variant)

- Extended `flashShortcutCopyBadge(mode)` in `tasks.php` with a new `filter` mode:
  - label: `Filter`
  - warm amber styling
- Updated `Ctrl+Y` copy path to pass badge mode `filter` instead of reusing `snapshot`.
- Kept existing badge variants unchanged:
  - `Copied` for source-only copy
  - `Snapshot` for Shift+Y rich snapshot copy

### Important Files (Ctrl+Y Filter Badge Variant)

- tasks.php
- WORKLOG.md

### Validation (Ctrl+Y Filter Badge Variant)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `Ctrl+Y` shows `Filter` badge with distinct amber styling,
  - `Shift+Y` still shows `Snapshot` badge with blue styling.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 65)

### Scope (Ctrl+Y Source+Filter Copy)

- Add `Ctrl+Y` shortcut to copy source label plus current filter mode for reporting context.

### Key Changes (Ctrl+Y Source+Filter Copy)

- Added `buildSourceFilterSnapshot()` in `tasks.php` client logic:
  - captures current source label
  - captures active filter label (`All Events` / `Status Changes`)
- Added pre-guard keydown branch to handle `Ctrl+Y` before generic modifier-key return path.
- Wired `Ctrl+Y` to copy `Source: ... | Filter: ...` payload and show source/filter-specific success toast.
- Updated shortcut guidance in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`):
  - cheatline text
  - compact/detailed hint strings
  - shortcut help mapping
- Updated keyboard source tooltip shortcut list to include `Ctrl+Y`.

### Important Files (Ctrl+Y Source+Filter Copy)

- tasks.php
- WORKLOG.md

### Validation (Ctrl+Y Source+Filter Copy)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - cheatline/help include `Ctrl+Y`,
  - `Ctrl+Y` updates key status with source+filter action,
  - toast shows copied `Source: ... | Filter: ...` payload.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 64)

### Scope (Snapshot Toast Length Suffix)

- Add snapshot length metadata to Shift+Y toast preview output.

### Key Changes (Snapshot Toast Length Suffix)

- Updated `snapshotPreviewToastText(snapshotText)` in `tasks.php` to append character count suffix.
- Shift+Y success toast now follows format:
  - `Snapshot copied: <preview> (<n> chars)`
- Kept existing truncation behavior and source-only (`Y`) toast behavior unchanged.

### Important Files (Snapshot Toast Length Suffix)

- tasks.php
- WORKLOG.md

### Validation (Snapshot Toast Length Suffix)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `Shift+Y` toast includes snapshot preview plus `(<n> chars)` suffix,
  - key status remains accurate for snapshot copy action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 63)

### Scope (Snapshot Toast Preview)

- Show the copied snapshot payload in the `Shift+Y` success toast with readable truncation.

### Key Changes (Snapshot Toast Preview)

- Added `snapshotPreviewToastText(snapshotText)` in `tasks.php` client logic.
- Implemented safe ASCII truncation (`...`) for long snapshot payloads to keep toast output concise.
- Updated `Shift+Y` handler path to pass the snapshot preview string as the success toast text.
- Preserved existing copy behavior split:
  - `Y` keeps source-only toast text,
  - `Shift+Y` now shows snapshot content preview.

### Important Files (Snapshot Toast Preview)

- tasks.php
- WORKLOG.md

### Validation (Snapshot Toast Preview)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `Y` toast remains `Source copied: <Source>.`,
  - `Shift+Y` toast now shows `Snapshot copied: Source: ... | Hint: ... | Toasts: ...`,
  - key status remains accurate for `Shift+Y` copy action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 62)

### Scope (Copy Badge Variants)

- Differentiate copy badge feedback by action type:
  - `Copied` for source-only copy (`Y` / glyph click)
  - `Snapshot` for rich snapshot copy (`Shift+Y`)

### Key Changes (Copy Badge Variants)

- Updated `flashShortcutCopyBadge(...)` in `tasks.php` client logic to accept mode and apply mode-specific label/colors.
- Extended `copyCurrentSourceLabel(...)` to accept badge mode and route success feedback accordingly.
- Updated key handlers to pass explicit modes:
  - `Y` -> `copied`
  - `Shift+Y` -> `snapshot`
- Preserved existing auto-hide timing and error handling behavior.

### Important Files (Copy Badge Variants)

- tasks.php
- WORKLOG.md

### Validation (Copy Badge Variants)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - `Y` shows `Copied` badge with green styling,
  - `Shift+Y` shows `Snapshot` badge with blue styling,
  - both variants auto-hide after timeout,
  - key status reflects `Shift+Y` snapshot copy action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 61)

### Scope (Shift+Y Rich Snapshot Copy)

- Add `Shift+Y` shortcut to copy a richer status snapshot (`Source`, `Hint`, `Toasts`) from panel focus.

### Key Changes (Shift+Y Rich Snapshot Copy)

- Added `buildRichSourceSnapshot()` in `tasks.php` client logic to compose:
  - `Source: <...> | Hint: <Compact|Detailed> | Toasts: <On|Muted>`
- Extended `copyCurrentSourceLabel(...)` to accept optional explicit payload and custom success toast text while preserving existing `Y` and glyph-click behavior.
- Added key handler branch for `Shift+Y` (before plain `Y`) to trigger rich snapshot copy.
- Updated shortcut guidance text in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`):
  - cheatline
  - compact/detailed shortcut hint strings
  - shortcut help mapping
- Updated keyboard tooltip wording to include `Y` and `Shift+Y` in the listed shortcut set.

### Important Files (Shift+Y Rich Snapshot Copy)

- tasks.php
- WORKLOG.md

### Validation (Shift+Y Rich Snapshot Copy)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - cheatline/help include `Shift+Y`,
  - `Y` keeps source-only copy status,
  - `Shift+Y` triggers snapshot copy status,
  - copied badge still shows and auto-hides after timeout.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 60)

### Scope (Temporary Copied Badge)

- Add a short-lived `Copied` badge near the source glyph after successful source copy (click or `Y`).

### Key Changes (Temporary Copied Badge)

- Added `.js-audit-shortcut-copy-badge` in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) directly beside source glyph.
- Added timer-driven helper in `tasks.php` client logic:
  - `flashShortcutCopyBadge()`
  - shows badge immediately on successful copy
  - auto-hides after ~1.1s
- Reused existing copy success path so badge triggers for both:
  - glyph click copy
  - keyboard `Y` copy shortcut

### Important Files (Temporary Copied Badge)

- tasks.php
- WORKLOG.md

### Validation (Temporary Copied Badge)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - badge hidden before copy,
  - badge visible right after successful `Y` copy,
  - badge auto-hides after timeout window,
  - key status copy feedback remains intact.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 59)

### Scope (Keyboard Copy Shortcut)

- Add keyboard shortcut `Y` to copy current source label directly from panel focus.

### Key Changes (Keyboard Copy Shortcut)

- Updated shortcut hint strings in `tasks.php` to include `Y` in compact and detailed hint text.
- Updated help/cheatline text in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to document `Y = Copy current source label`.
- Added `Y` handling in panel keydown handler to trigger source copy action without moving focus.
- Refined `copyCurrentSourceLabel()` to accept a status prefix so keyboard copy and glyph-click copy can report distinct key-status labels.

### Important Files (Keyboard Copy Shortcut)

- tasks.php
- WORKLOG.md

### Validation (Keyboard Copy Shortcut)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - cheatline includes `Y`,
  - shortcut help includes copy-source mapping for `Y`,
  - pressing `Y` updates key status with copied source label.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 58)

### Scope (Glyph Copy Action)

- Make the source glyph clickable to copy the current source label and provide immediate feedback.

### Key Changes (Glyph Copy Action)

- Updated source glyph markup in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) from passive span to interactive button (`.js-audit-shortcut-state-glyph`).
- Added clipboard helper logic in `tasks.php` client behavior:
  - `copyTextToClipboard(text)` with `navigator.clipboard.writeText` and `execCommand('copy')` fallback
  - `copyCurrentSourceLabel()` wrapper for source-specific copy action
- Wired glyph click handler to run copy action and report result via existing feedback channels:
  - key status line update
  - toast message
- Updated dynamic glyph accessibility text to reflect current source (`aria-label`) and copy affordance (`title`).

### Important Files (Glyph Copy Action)

- tasks.php
- WORKLOG.md

### Validation (Glyph Copy Action)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - glyph renders as `BUTTON` element,
  - glyph click triggers copy flow,
  - key status updates to `Glyph copy -> <Source>`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 57)

### Scope (Meaning-Line Highlight Pulse)

- Add a brief highlight pulse to the current source meaning line whenever source changes, then ease back to baseline.

### Key Changes (Meaning-Line Highlight Pulse)

- Updated `.js-audit-source-meaning` base styling in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to support smooth highlight transitions.
- Added `sourceMeaningPulseResetTimer` and `pulseSourceMeaning(sourceLabel)` in `tasks.php` client logic.
- Reused existing source accent palette so meaning-line pulse color matches Keyboard/Button/Session source context.
- Hooked meaning-line pulse into `setLastSettingSource()` so it fires only when source actually changes (same trigger guard used by source label pulse).

### Important Files (Meaning-Line Highlight Pulse)

- tasks.php
- WORKLOG.md

### Validation (Meaning-Line Highlight Pulse)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - meaning line briefly highlights on Keyboard and Button source changes,
  - highlight transitions back to baseline style after pulse window,
  - source summary behavior remains stable.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 56)

### Scope (Source Glyph Marker)

- Add a compact source glyph marker beside the Source value that changes by source type.

### Key Changes (Source Glyph Marker)

- Updated shortcut-state markup in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to add `.js-audit-shortcut-state-glyph` before source text.
- Added `sourceGlyphText(sourceLabel)` helper in `tasks.php` client logic:
  - `[K]` for Keyboard
  - `[B]` for Button
  - `[S]` for Session
- Updated `refreshShortcutStateSummary()` to keep glyph synchronized with source label and reuse source accent colors for glyph border/text/background.
- Preserved existing source pulse, tooltip, freshness, and meaning-line behavior.

### Important Files (Source Glyph Marker)

- tasks.php
- WORKLOG.md

### Validation (Source Glyph Marker)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - initial source glyph renders `[S]` with Session accent,
  - keyboard action updates glyph to `[K]` with Keyboard accent,
  - button action updates glyph to `[B]` with Button accent.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 55)

### Scope (Live Source Meaning Line)

- Add a compact always-visible line under source legend that explains what the current Source means.

### Key Changes (Live Source Meaning Line)

- Added `.js-audit-source-meaning` row in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`).
- Added `sourceMeaningText(sourceLabel)` helper in `tasks.php` client logic for source-specific messaging:
  - Session meaning text
  - Keyboard meaning text
  - Button meaning text
- Wired meaning-line updates into `refreshShortcutStateSummary()` so the text updates immediately when source changes.
- Kept existing tooltip and pulse behavior unchanged.

### Important Files (Live Source Meaning Line)

- tasks.php
- WORKLOG.md

### Validation (Live Source Meaning Line)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - initial line shows Session meaning,
  - keyboard action updates line to Keyboard meaning,
  - button action updates line to Button meaning.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 54)

### Scope (Always-Visible Source Legend)

- Add an always-visible source legend line under shortcut state for touch and non-hover contexts.

### Key Changes (Always-Visible Source Legend)

- Updated shortcut-state block in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to include a new legend row:
  - `.js-audit-source-legend`
  - color-dot labels for Keyboard, Button, and Session sources
- Kept the existing source pulse palette aligned with the same source colors shown in legend dots.
- Minor spacing adjustment in shortcut-state margin to fit legend line cleanly.

### Important Files (Always-Visible Source Legend)

- tasks.php
- WORKLOG.md

### Validation (Always-Visible Source Legend)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - legend line renders in open audit panel,
  - legend text includes Keyboard, Button, and Session,
  - summary/source behavior remains unchanged.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 53)

### Scope (Source Tooltip Guidance)

- Add a concise explanatory tooltip on the shortcut-state Source value.

### Key Changes (Source Tooltip Guidance)

- Updated shortcut-state source markup in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to include default Session tooltip text.
- Added `sourceTooltipText(sourceLabel)` helper in `tasks.php` client logic with source-specific guidance:
  - Keyboard: changed through panel shortcuts
  - Button: changed via panel controls
  - Session: restored from saved row/session preferences
- Updated summary refresh flow to keep source tooltip synchronized whenever source label changes.

### Important Files (Source Tooltip Guidance)

- tasks.php
- WORKLOG.md

### Validation (Source Tooltip Guidance)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - initial Session source tooltip text is present,
  - source tooltip switches to Keyboard text after shortcut action,
  - source tooltip switches to Button text after button action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 52)

### Scope (Source-Specific Pulse Accents)

- Add distinct pulse accent colors for Source changes by source type.

### Key Changes (Source-Specific Pulse Accents)

- Added `sourcePulseAccent(sourceLabel)` in `tasks.php` client logic to map source -> accent palette:
  - Keyboard: teal accent
  - Button: blue accent
  - Session: violet accent fallback
- Updated `pulseShortcutSource(sourceLabel)` to use the mapped accent colors instead of a single shared color.
- Kept pulse timing and reset behavior unchanged so existing micro-interaction cadence remains stable.
- Preserved source-change guard in `setLastSettingSource()` so pulse still triggers only when source label actually changes.

### Important Files (Source-Specific Pulse Accents)

- tasks.php
- WORKLOG.md

### Validation (Source-Specific Pulse Accents)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - Keyboard-triggered source pulse uses teal tint,
  - Button-triggered source pulse uses blue tint,
  - summary/source behavior remains consistent with existing freshness flow.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 51)

### Scope (Source Pulse Feedback)

- Add a subtle pulse highlight on the shortcut-state Source value when the source label changes.

### Key Changes (Source Pulse Feedback)

- Refined shortcut-state markup in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to split source into dedicated spans:
  - `.js-audit-shortcut-state-prefix`
  - `.js-audit-shortcut-state-source`
  - existing `.js-audit-source-freshness`
- Added `pulseShortcutSource()` helper in `tasks.php` client logic:
  - short scale-up + color/background accent pulse,
  - timed reset to baseline style.
- Updated `setLastSettingSource()` to pulse only when source actually changes (`previousSource !== sourceLabel`), preventing repeated pulses for same-source actions.
- Updated shortcut summary refresh logic to compose prefix/source/freshness parts independently while preserving existing freshness fade behavior.

### Important Files (Source Pulse Feedback)

- tasks.php
- WORKLOG.md

### Validation (Source Pulse Feedback)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - source value pulses on Session -> Keyboard transition,
  - source pulse resets to baseline,
  - repeated same-source keyboard action does not pulse again,
  - freshness token behavior remains intact.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 50)

### Scope (Freshness Fade Motion)

- Add subtle fade-out motion to the `just now` freshness token in shortcut state summary.

### Key Changes (Freshness Fade Motion)

- Updated shortcut summary markup in both render paths (`tasks.php` PHP renderer and JS `renderAuditHistory`) to split state text into:
  - base summary span (`.js-audit-shortcut-state-base`)
  - freshness span (`.js-audit-source-freshness`)
- Refactored `refreshShortcutStateSummary()` to update base text separately and drive freshness visibility/opacity without replacing the whole summary node.
- Reworked freshness timers in `markSettingFreshness()`:
  - start visible at full opacity,
  - switch to fading state after ~2.8s,
  - clear freshness token after ~4.2s.
- Added explicit fade/clear timer tracking:
  - `lastSettingFreshnessFading`
  - `lastSettingFreshnessFadeTimer`
  - `lastSettingFreshnessClearTimer`

### Important Files (Freshness Fade Motion)

- tasks.php
- WORKLOG.md

### Validation (Freshness Fade Motion)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirmed:
  - freshness token displays as `| just now` at opacity 1 after a setting change,
  - token transitions toward opacity 0 during fade window,
  - token hides (`display:none`) after clear timeout,
  - base summary remains stable and accurate after token removal.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 49)

### Scope (Shortcut Freshness Token)

- Add a tiny freshness token (`just now`) beside the shortcut-state source indicator after setting changes, then auto-clear it.

### Key Changes (Shortcut Freshness Token)

- Added freshness state tracking in `tasks.php`:
  - `lastSettingFreshness`
  - `lastSettingFreshnessTimer`
  - `markSettingFreshness()` helper
- Updated shortcut state summary rendering in `tasks.php` to append freshness suffix when active:
  - `... | Source <...> | just now`
- Wired freshness marking into setting mutation paths:
  - hint mode transitions,
  - mute toggle transitions,
  - explicit unmute transitions.
- Implemented auto-clear timeout (about 4.2s) to remove freshness token without user action.

### Important Files (Shortcut Freshness Token)

- tasks.php
- WORKLOG.md

### Validation (Shortcut Freshness Token)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - after a setting change, summary includes `| just now`,
  - after timeout window, token auto-clears while summary state remains correct.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 48)

### Scope (Normalized Source Label Formatting)

- Normalize trigger-to-source mapping so shortcut-state `Source` labels are derived consistently from one formatter.

### Key Changes (Normalized Source Label Formatting)

- Added source formatter helper in `tasks.php`:
  - `sourceLabelFromTrigger(triggerLabel)`
  - maps trigger text to `Keyboard`, `Button`, or `Session`.
- Refactored hint/mute transition flows in `tasks.php` to use formatter-driven source updates:
  - hint mode transitions,
  - mute toggle transitions,
  - explicit unmute transitions.
- Removed ad-hoc source-label branching so future triggers inherit consistent source behavior.

### Important Files (Normalized Source Label Formatting)

- tasks.php
- WORKLOG.md

### Validation (Normalized Source Label Formatting)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms source consistency:
  - keyboard actions -> `Source Keyboard`,
  - button actions -> `Source Button`,
  - initial state -> `Source Session`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 47)

### Scope (Last Setting Source Indicator)

- Add lightweight `Source` context (Keyboard vs Button vs Session) beside the shortcut state summary line.

### Key Changes (Last Setting Source Indicator)

- Updated `tasks.php` shortcut state summary default text (server-rendered and JS-rendered):
  - `Shortcut state: Hint Compact | Toasts On | Source Session`
- Added source-state tracking in `tasks.php`:
  - `lastSettingSource` variable (initial `Session`),
  - `setLastSettingSource(sourceLabel)` helper.
- Extended summary refresh logic to include source:
  - `Shortcut state: Hint <...> | Toasts <...> | Source <...>`
- Wired source updates into setting mutation paths:
  - keyboard hint/toggle actions -> `Source Keyboard`,
  - button-based actions -> `Source Button`,
  - initial/restored state remains `Source Session`.

### Important Files (Last Setting Source Indicator)

- tasks.php
- WORKLOG.md

### Validation (Last Setting Source Indicator)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms state transitions:
  - initial: `Source Session`,
  - keyboard action: `Source Keyboard`,
  - button action: `Source Button`,
  - subsequent keyboard action returns to `Source Keyboard`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 46)

### Scope (Shift+M Explicit Unmute)

- Add `Shift+M` as an explicit unmute shortcut while preserving `M` as a mute/unmute toggle.

### Key Changes (Shift+M Explicit Unmute)

- Updated `tasks.php` shortcut discoverability text (server-rendered + JS-rendered):
  - cheat line includes `Shift+M`,
  - compact/detailed shortcut hint strings include `Shift+M`,
  - help popover includes `Shift+M = Unmute hint toasts`.
- Added explicit unmute helper in `tasks.php`:
  - `applyHintToastUnmute(statusText, triggerLabel)`
  - unmute action path updates UI + storage + key status + toast + aria-live.
- Extended focused-shell keyboard handling in `tasks.php`:
  - `Shift+M` performs explicit unmute,
  - if already unmuted, records no-change status.
- Kept `M` behavior as toggle with existing mute-state feedback flows.

### Important Files (Shift+M Explicit Unmute)

- tasks.php
- WORKLOG.md

### Validation (Shift+M Explicit Unmute)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - discoverability text includes `Shift+M`,
  - from muted state, `Shift+M` unmuted state transition works,
  - key status shows `Shift+M -> Hint toasts unmuted`,
  - aria-live announces `Hint toasts: Unmuted. Trigger: keyboard Shift+M.`,
  - repeated `Shift+M` when already unmuted reports no-change status.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 45)

### Scope (Current Shortcut State Line)

- Add a compact one-line summary in each history panel showing current shortcut state (hint mode + toast mute state).

### Key Changes (Current Shortcut State Line)

- Added shortcut state line to `tasks.php` history markup (server-rendered and JS-rendered):
  - `.js-audit-shortcut-state`
  - default: `Shortcut state: Hint Compact | Toasts On`
- Added state sync helper in `tasks.php`:
  - `refreshShortcutStateSummary()`
- Wired summary refresh into existing mode/mute update flows:
  - hint mode changes (`H` / `Shift+H` / reset button),
  - mute toggle changes (`M` / mute toggle button).
- Added subtle tone shift when toasts are muted for quicker scanning.

### Important Files (Current Shortcut State Line)

- tasks.php
- WORKLOG.md

### Validation (Current Shortcut State Line)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms summary updates correctly through sequence:
  - Compact/On -> Detailed/On -> Detailed/Muted -> Compact/Muted.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 44)

### Scope (Mute Toggle Aria-Live Feedback)

- Add aria-live announcements for hint-toast mute/unmute state changes to match keyboard/button accessibility feedback.

### Key Changes (Mute Toggle Aria-Live Feedback)

- Added helper in `tasks.php`:
  - `announceHintToastMute(muted, triggerLabel)`
- Wired helper into shared mute toggle flow in `tasks.php`:
  - announces `Hint toasts: Muted/Unmuted` for both trigger types,
  - trigger labels distinguish `toggle button` and `keyboard M`.
- Reused existing live-region debounce/timer behavior to avoid announcement flooding.

### Important Files (Mute Toggle Aria-Live Feedback)

- tasks.php
- WORKLOG.md

### Validation (Mute Toggle Aria-Live Feedback)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - button mute action announces `Hint toasts: Muted. Trigger: toggle button.`,
  - keyboard `M` action announces `Hint toasts: Unmuted. Trigger: keyboard M.`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 43)

### Scope (M Shortcut + Discoverability)

- Add keyboard shortcut `M` to toggle per-row hint-toast mute on the focused history panel.
- Update hint/cheat/help copy so mute controls are clearly discoverable.

### Key Changes (M Shortcut + Discoverability)

- Updated `tasks.php` shortcut copy (server-rendered and JS-rendered):
  - cheat line includes `M`,
  - compact/detailed shortcut hint strings include `M`,
  - shortcut help includes `M = Toggle hint toasts mute`.
- Added shared helper in `tasks.php`:
  - `applyHintToastMuteToggle(statusPrefix)`
  - reused by both mute button click path and new keyboard path.
- Extended focused-shell keyboard handling in `tasks.php`:
  - `M` toggles mute/unmute,
  - key status updates (`M -> Hint toasts muted/unmuted`),
  - existing mute toggle UI/chip state updates remain in sync.

### Important Files (M Shortcut + Discoverability)

- tasks.php
- WORKLOG.md

### Validation (M Shortcut + Discoverability)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - cheat/hint/help text all include `M`,
  - pressing `M` toggles muted chip visibility and toggle label,
  - key status reflects muted/unmuted states correctly.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 42)

### Scope (Muted State Chip)

- Add a subtle inline visual indicator so muted hint-toast state is visible at a glance.

### Key Changes (Muted State Chip)

- Updated `tasks.php` shortcut hint markup (server-rendered and JS-rendered) to include:
  - `.js-audit-hint-toast-muted-chip` (`Muted`).
- Integrated muted chip into hint rerender lifecycle so it stays attached when hint text redraws.
- Extended `refreshHintToastToggle()` in `tasks.php`:
  - toggles chip visibility with mute state,
  - keeps existing mute/unmute button text + aria-pressed behavior.
- Preserved per-row session persistence introduced in Slice 40.

### Important Files (Muted State Chip)

- tasks.php
- WORKLOG.md

### Validation (Muted State Chip)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - chip hidden by default,
  - chip appears after muting,
  - chip remains visible after close/reopen (persisted state),
  - chip hides again after unmuting.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 41)

### Scope (Centralized Hint Message Formatter)

- Centralize hint action messaging so key status, hint toast, and aria-live text stay consistent across all mode-change triggers.

### Key Changes (Centralized Hint Message Formatter)

- Added shared formatter in `tasks.php`:
  - `getHintActionMessages(nextDetailed, triggerKey)`
- Updated centralized transition flow in `tasks.php` to consume formatter output for:
  - key status text,
  - hint toast text,
  - aria-live destination mode + trigger label.
- Simplified reset and keyboard paths to pass trigger keys rather than hand-built text:
  - `H`, `Shift+H`, and `Reset hint` button now all route through shared message generation.
- Preserved no-change path behavior (`Shift+H` in compact still reports `(no change)` and does not announce).

### Important Files (Centralized Hint Message Formatter)

- tasks.php
- WORKLOG.md

### Validation (Centralized Hint Message Formatter)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - `H` detailed transition text remains correct across live/status/toast,
  - reset-button compact transition text remains correct across live/status/toast,
  - compact-state `Shift+H` no-change status remains intact.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 40)

### Scope (Per-Row Mute Hint Toasts)

- Add a per-row session preference to mute hint-mode toasts while keeping aria-live announcements active.

### Key Changes (Per-Row Mute Hint Toasts)

- Added session storage support in `tasks.php`:
  - key prefix: `taskAuditHintToastMute:`
  - helpers: `isHintToastMuted(taskId)`, `setHintToastMuted(taskId, muted)`
- Added inline toggle control to shortcut hint area (server-rendered and JS-rendered):
  - `.js-audit-hint-toast-toggle`
  - toggles text `Mute hint toasts` / `Unmute hint toasts`
- Wired per-row toggle behavior in `tasks.php`:
  - persists preference,
  - refreshes toggle UI state,
  - records key status and confirmation toast on toggle action.
- Updated centralized hint transition helper flow so hint-mode toasts are conditionally suppressed when muted, while aria-live announcements remain unchanged.

### Important Files (Per-Row Mute Hint Toasts)

- tasks.php
- WORKLOG.md

### Validation (Per-Row Mute Hint Toasts)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - mute toggle state text switches correctly,
  - with mute enabled, `H` still updates aria-live but shows no hint-mode toast,
  - with mute disabled, hint-mode toast reappears as expected.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 39)

### Scope (Centralized Hint Mode Transition)

- Centralize hint mode changes so no-change suppression, persistence, announcements, key status, and toasts are applied consistently across all hint controls.

### Key Changes (Centralized Hint Mode Transition)

- Added shared transition helper in `tasks.php`:
  - `applyHintModeTransition(nextDetailed, options)`
- Refactored existing mode-changing paths to use shared helper:
  - `H` toggle shortcut,
  - `Shift+H` compact reset (via `resetHintToCompact` wrapper),
  - `Reset hint` button path.
- Preserved no-change behavior:
  - redundant compact reset remains a no-op for aria-live and toast,
  - key status still records `Shift+H -> Hint compact (no change)`.

### Important Files (Centralized Hint Mode Transition)

- tasks.php
- WORKLOG.md

### Validation (Centralized Hint Mode Transition)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - compact-state `Shift+H` causes zero live-region mutations,
  - `H` toggles to detailed with correct live message + badge,
  - `Reset hint` returns to compact with correct live message + key status.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 38)

### Scope (Suppress Unchanged Announcements)

- Prevent aria-live announcements when hint mode does not actually change.

### Key Changes (Suppress Unchanged Announcements)

- Updated `resetHintToCompact(...)` in `tasks.php`:
  - early-return no-op when already in compact mode,
  - only announces/stores/toasts when a real detailed -> compact transition occurs,
  - returns a boolean change flag.
- Updated `Shift+H` handler in `tasks.php`:
  - uses returned change flag,
  - records key status with `(no change)` when reset is redundant,
  - avoids redundant live announcements in that case.

### Important Files (Suppress Unchanged Announcements)

- tasks.php
- WORKLOG.md

### Validation (Suppress Unchanged Announcements)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - `Shift+H` while already compact causes zero live-region mutations,
  - actual compact transition from detailed still announces correctly.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 37)

### Scope (Aria-Live Debounce)

- Reduce screen-reader announcement noise by debouncing rapid hint-mode changes.

### Key Changes (Aria-Live Debounce)

- Added debounce state in `tasks.php` hint shell logic:
  - `hintLiveDebounceMs = 120`
  - `hintLiveTimer`
- Updated `announceHintMode(...)` in `tasks.php`:
  - rapid calls now collapse into a single delayed live-region update,
  - final message reflects the latest mode/trigger event.
- Kept all existing hint mode controls and persistence behavior unchanged.

### Important Files (Aria-Live Debounce)

- tasks.php
- WORKLOG.md

### Validation (Aria-Live Debounce)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - rapid repeated `H` key presses produce a single live-region mutation,
  - final announcement text reflects the last resulting hint mode.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 36)

### Scope (Reliable Live Destination Announcements)

- Refine hint-mode aria-live announcements so they consistently report the destination mode (`Detailed` or `Compact`) with concise trigger wording.

### Key Changes (Reliable Live Destination Announcements)

- Updated live-region message format in `tasks.php` to concise output:
  - `Hint mode: <Mode>. Trigger: <Action>.`
- Fixed `H` toggle path in `tasks.php` to compute target mode before mutating state:
  - prevents ambiguous/stale mode labeling,
  - keeps key status, toast text, and stored mode aligned with the same destination mode.
- Kept `Shift+H` and reset-button announcement triggers explicit and consistent.

### Important Files (Reliable Live Destination Announcements)

- tasks.php
- WORKLOG.md

### Validation (Reliable Live Destination Announcements)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - first `H` from compact announces `Hint mode: Detailed. Trigger: keyboard H.`,
  - second `H` announces `Hint mode: Compact. Trigger: keyboard H.`,
  - `Shift+H` announces `Hint mode: Compact. Trigger: keyboard Shift+H.`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 35)

### Scope (Aria-Live Hint Announcements)

- Add screen-reader friendly live announcements whenever hint mode changes via keyboard or reset button actions.

### Key Changes (Aria-Live Hint Announcements)

- Added a hidden `aria-live="polite"` region in `tasks.php` history shell markup (server-rendered and JS-rendered):
  - `.js-audit-hint-live`
- Added announcement helper in `tasks.php`:
  - `announceHintMode(modeLabel, triggerLabel)`
- Wired live announcements to all mode-changing interactions:
  - `H` toggle shortcut,
  - `Shift+H` compact reset,
  - `Reset hint` button compact reset.
- Kept existing hint badge, key-status, toast, and persistence behavior unchanged.

### Important Files (Aria-Live Hint Announcements)

- tasks.php
- WORKLOG.md

### Validation (Aria-Live Hint Announcements)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms live-region messages update for:
  - keyboard `H`,
  - keyboard `Shift+H`,
  - `Reset hint` button click.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 34)

### Scope (One-Time Restored Hint Toast)

- Add a one-time toast when opening a task history row that restores detailed hint mode from session preference.

### Key Changes (One-Time Restored Hint Toast)

- Added in-memory tracking in `tasks.php` for per-row restore notifications:
  - `detailedHintRestoreToastShown` set keyed by task id.
- Extended `openHistoryRow(...)` in `tasks.php`:
  - on open, if stored hint mode is `detailed` and row has not yet shown restore notice,
  - show toast: `Hint mode restored from session: Detailed.`
  - mark row as already notified for this page session.
- Preserved existing open/focus/toggle behavior with no changes to filter logic.

### Important Files (One-Time Restored Hint Toast)

- tasks.php
- WORKLOG.md

### Validation (One-Time Restored Hint Toast)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - after storing detailed mode, first reopen shows restore toast,
  - subsequent reopen of same row suppresses repeated restore toast,
  - no regressions in existing hint/badge/reset keyboard behaviors.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 33)

### Scope (Inline Reset Hint Control)

- Add a tiny clickable `Reset hint` control beside the hint mode badge to force compact mode with persistence.

### Key Changes (Inline Reset Hint Control)

- Updated `tasks.php` shortcut hint markup (server-rendered and JS-rendered) to include:
  - `.js-audit-hint-reset` button next to `.js-audit-hint-mode-badge`.
- Extended hint rendering/state helper logic in `tasks.php`:
  - reattach/reset control when hint text rerenders,
  - disable reset button while already in compact mode,
  - enable it when in detailed mode.
- Added shared compact reset helper in `tasks.php` and reused it for:
  - existing `Shift+H` shortcut path,
  - new `Reset hint` button click path.
- Updated shortcut help text to include button behavior (`Reset hint button = Compact`).

### Important Files (Inline Reset Hint Control)

- tasks.php
- WORKLOG.md

### Validation (Inline Reset Hint Control)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - reset button is disabled in compact mode,
  - button enables in detailed mode,
  - clicking button resets to compact and updates key status,
  - reopened row restores compact mode,
  - help text includes reset button guidance.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 32)

### Scope (Shift+H Compact Reset)

- Add a direct keyboard reset path for hint mode so users can force compact mode without cycling.

### Key Changes (Shift+H Compact Reset)

- Updated `tasks.php` keyboard handling:
  - `Shift+H` now resets hint mode to compact immediately,
  - `H` continues to toggle compact/detailed mode.
- Persisted reset behavior in `tasks.php` via existing per-row hint mode storage:
  - `Shift+H` writes `compact` to session storage for the active row.
- Updated shortcut help text (server-rendered and JS-rendered) to include:
  - `Shift+H = Reset hint compact`
- Updated detailed hint copy to document the compact reset shortcut.

### Important Files (Shift+H Compact Reset)

- tasks.php
- WORKLOG.md

### Validation (Shift+H Compact Reset)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - `H` toggles to detailed mode,
  - `Shift+H` resets to compact mode,
  - key status updates to `Shift+H -> Hint compact`,
  - reopening the row restores compact mode and matching badge.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 31)

### Scope (Inline Hint Mode Badge)

- Add a small inline badge beside the shortcut hint showing current mode (`Hint: Compact` or `Hint: Detailed`).

### Key Changes (Inline Hint Mode Badge)

- Updated `tasks.php` history markup (server-rendered and JS-rendered) to include:
  - `<span class="js-audit-hint-mode-badge">...` inside the shortcut hint line.
- Extended `setShortcutHintDetailed(...)` in `tasks.php` to keep badge synchronized with mode changes:
  - text updates to compact/detailed,
  - tone colors update for clearer state visibility.
- Preserved per-row persisted hint mode behavior from Slice 30 so badge state restores when rows reopen.

### Important Files (Inline Hint Mode Badge)

- tasks.php
- WORKLOG.md

### Validation (Inline Hint Mode Badge)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - badge renders in shortcut hint line,
  - pressing `H` toggles badge text and hint mode,
  - reopening row restores persisted mode and matching badge text.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 30)

### Scope (Persist Hint Mode Per Row)

- Persist each audit-history panel's compact/detailed shortcut hint preference for the current browser session.

### Key Changes (Persist Hint Mode Per Row)

- Added per-row hint mode session storage in `tasks.php`:
  - key prefix: `taskAuditHintMode:`
  - values: `compact` or `detailed`
- Added storage helpers in `tasks.php`:
  - `getStoredAuditHintMode(taskId)`
  - `setStoredAuditHintMode(taskId, mode)`
- Wired hint mode initialization so each panel restores its own prior mode when opened.
- Extended `H` shortcut handling to save mode immediately after toggle.

### Important Files (Persist Hint Mode Per Row)

- tasks.php
- WORKLOG.md

### Validation (Persist Hint Mode Per Row)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - panel starts compact by default,
  - pressing `H` switches to detailed mode,
  - closing and reopening the same row restores detailed mode,
  - a different row remains compact (per-row isolation).

## 2026-06-04 - Nutshell Improvements (Task Module Slice 29)

### Scope (Hint Detail Toggle)

- Add a one-key inline legend state toggle so focused audit-history panels can switch between compact and detailed shortcut hint text.

### Key Changes (Hint Detail Toggle)

- Updated `tasks.php` history markup (server-rendered and JS-rendered) to:
  - include `H` in compact key legend text,
  - keep the shortcut help popover available via `?`.
- Added hint-mode state and text helpers in `tasks.php`:
  - compact hint mode: `Shortcuts: A S R C G H ? Esc`
  - detailed hint mode: expanded key meanings.
- Extended focused-shell keyboard handling:
  - `H` toggles compact/detailed inline hint mode,
  - key status updates (`H -> Hint detailed/compact`),
  - toast confirms current hint mode.

### Important Files (Hint Detail Toggle)

- tasks.php
- WORKLOG.md

### Validation (Hint Detail Toggle)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - initial hint is compact,
  - pressing `H` switches to detailed hint and back on second press,
  - pressing `?` still toggles help popover.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 28)

### Scope (Compact Focus Cheat Line)

- Add a compact, focus-scoped keyboard cheat line to audit-history panels for faster shortcut discovery.

### Key Changes (Compact Focus Cheat Line)

- Updated `tasks.php` history markup (server-rendered and JS-rendered) to add a compact pill-style line:
  - `Keys: A S R C G ? Esc`
- Extended focus lifecycle handling in `tasks.php` so the compact cheat line:
  - appears when panel focus enters,
  - hides when focus leaves.
- Kept existing detailed shortcut hint/help behavior unchanged and compatible with the new compact line.

### Important Files (Compact Focus Cheat Line)

- tasks.php
- WORKLOG.md

### Validation (Compact Focus Cheat Line)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - compact cheat line text renders correctly,
  - line hides on blur and reappears on refocus,
  - existing shortcut hint/help remains functional.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 27)

### Scope (C Shortcut for Clear Overrides)

- Add `C` keyboard shortcut in focused audit-history panels to run `Clear row overrides (visible)` with existing undo behavior.

### Key Changes (C Shortcut for Clear Overrides)

- Updated `tasks.php` shortcut hint/help text (server-rendered and JS-rendered) to include:
  - `C = Clear overrides`
- Refactored clear-visible flow into a shared helper in `tasks.php` and reused it for:
  - clear-visible button click,
  - focused-panel `C` keyboard action.
- Extended focused-shell keyboard handling:
  - `C` triggers clear-visible workflow,
  - key status updates to `C -> Clear overrides`,
  - existing undo toast flow remains unchanged and reusable.

### Important Files (C Shortcut for Clear Overrides)

- tasks.php
- WORKLOG.md

### Validation (C Shortcut for Clear Overrides)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - shortcut hint includes `C = Clear overrides`,
  - pressing `C` triggers clear-visible toast with undo,
  - source transitions to default after clear,
  - undo restores prior row override state (e.g., `Source: Row (Status Changes)`).

## 2026-06-04 - Nutshell Improvements (Task Module Slice 26)

### Scope (G Shortcut for Global Mode)

- Add `G` keyboard shortcut in focused audit-history panels to toggle `Remember for all rows` global mode.

### Key Changes (G Shortcut for Global Mode)

- Updated `tasks.php` shortcut hint/help text (server-rendered and JS-rendered) to include global shortcut usage:
  - `G = Global mode`
- Extended focused-shell keyboard handler in `tasks.php`:
  - `G` toggles remember checkbox state,
  - dispatches checkbox `change` event to reuse existing global-state logic,
  - updates key status line (`G -> Global mode On/Off`),
  - shows user feedback toast (`Global mode enabled/disabled`).
- Preserved existing sync behavior so global badge and shared checkbox state update consistently through existing handlers.

### Important Files (G Shortcut for Global Mode)

- tasks.php
- WORKLOG.md

### Validation (G Shortcut for Global Mode)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - shortcut hint text includes `G = Global mode`,
  - pressing `G` toggles global badge Off -> On -> Off,
  - key status line updates to On/Off messages,
  - toast feedback matches enabled/disabled state.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 25)

### Scope (R Shortcut for Reset View)

- Add `R` keyboard shortcut in focused audit-history panels to run Reset view directly.

### Key Changes (R Shortcut for Reset View)

- Updated `tasks.php` shortcut hint/help text (server-rendered and JS-rendered) to include `R = Reset view`.
- Added a shared reset-view shortcut handler in `tasks.php` and reused it for both:
  - keyboard `R` action,
  - reset button click action.
- Extended focused-shell keyboard handling:
  - `R` clears row override and applies global/default fallback,
  - key status updates to `R -> Reset view`,
  - toast confirms reset (`History view reset.`).

### Important Files (R Shortcut for Reset View)

- tasks.php
- WORKLOG.md

### Validation (R Shortcut for Reset View)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - shortcut hint displays `R = Reset view`,
  - after `S`, panel source is `Row (Status Changes)`,
  - after `R`, panel source returns to `Default (All Events)`,
  - key status updates to `Last key action: R -> Reset view`,
  - reset toast appears.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 24)

### Scope (Last Key Action Status)

- Add a panel-local status line that records the most recent keyboard shortcut action in audit-history panels.

### Key Changes (Last Key Action Status)

- Updated `tasks.php` history markup (server-rendered and JS-rendered) to include hidden status text:
  - `Last key action: ...`
- Added shell-local status update helper in `tasks.php` and wired it to keyboard handlers:
  - `A` -> `A -> All Events`
  - `S` -> `S -> Status Changes`
  - `?` -> `? -> Help shown/hidden`
  - `Escape` -> `Escape -> Close panel`
- Kept status panel-local and lightweight (shown only after first key action).

### Important Files (Last Key Action Status)

- tasks.php
- WORKLOG.md

### Validation (Last Key Action Status)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - status line starts hidden,
  - pressing `S` updates status text correctly,
  - pressing `?` updates status to help toggle state,
  - pressing `Escape` closes panel,
  - reopening panel shows latest key-action message.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 23)

### Scope (Escape to Close Panel)

- Add `Escape` handling for focused audit-history panels so users can close the panel quickly and return to row context.

### Key Changes (Escape to Close Panel)

- Added shared history-panel helpers in `tasks.php`:
  - open panel by task id,
  - close panel by task id with optional focus return to toggle button.
- Refactored existing click toggle paths (standard toggle and replacement toggle after inline updates) to use shared open/close helpers.
- Extended focused-shell keyboard handler in `tasks.php`:
  - `Escape` closes current panel,
  - focus returns to the row’s `View last 3 events` toggle button.
- Ensured closed-state text remains consistent (`View last 3 events`) after keyboard close.

### Important Files (Escape to Close Panel)

- tasks.php
- WORKLOG.md

### Validation (Escape to Close Panel)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - panel opens and is visible,
  - pressing `Escape` while panel is focused closes the panel,
  - focus returns to the matching toggle button,
  - toggle label is `View last 3 events` after close.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 22)

### Scope (Question-Mark Help Toggle)

- Add a focused-panel `?` keyboard shortcut that toggles an inline mini-help popover for audit-history controls.

### Key Changes (Question-Mark Help Toggle)

- Updated `tasks.php` history markup (server-rendered and JS-rendered) to include a hidden shortcut help popover block.
- Added focused-shell helper toggle logic in `tasks.php`:
  - `?` (or `Shift+/`) toggles help visibility,
  - popover content summarizes available keyboard controls:
    - `A` = All Events
    - `S` = Status Changes
    - `?` = Toggle help
- Integrated with existing focus lifecycle:
  - help auto-hides when focus leaves the history shell,
  - behavior remains scoped to focused panel only.

### Important Files (Question-Mark Help Toggle)

- tasks.php
- WORKLOG.md

### Validation (Question-Mark Help Toggle)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - help starts hidden,
  - `Shift+/` toggles help to visible,
  - subsequent `Shift+/` toggles back to hidden,
  - help auto-hides when panel loses focus.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 21)

### Scope (Focus-Only Shortcut Hint)

- Add visible keyboard hint text for focused audit-history panels to improve shortcut discoverability.

### Key Changes (Focus-Only Shortcut Hint)

- Updated `tasks.php` history markup (server-rendered and JS-rendered) to include a compact hint line:
  - `Shortcuts: A = All Events, S = Status Changes`
- Implemented focus-scoped hint visibility in `tasks.php`:
  - hint is shown while focus is inside the history shell,
  - hint is hidden when focus leaves the shell.
- Used `focusin` / `focusout` handling with deferred active-element containment check for reliable hide behavior across nested controls.
- Kept existing keyboard shortcuts (`A`/`S`) unchanged and integrated with current override/global-summary behavior.

### Important Files (Focus-Only Shortcut Hint)

- tasks.php
- WORKLOG.md

### Validation (Focus-Only Shortcut Hint)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - opening a history panel focuses its shell and shows the hint,
  - `S` shortcut still activates status-changes chip,
  - after explicit shell blur, hint hides (`display: none`).

## 2026-06-04 - Nutshell Improvements (Task Module Slice 20)

### Scope (Keyboard Shortcuts)

- Add keyboard shortcuts for audit-history filter chips when a history panel is focused.

### Key Changes (Keyboard Shortcuts)

- Updated `tasks.php` history shell markup (server-rendered and JS-rendered) to be keyboard-focusable:
  - `tabindex="0"`
  - `aria-label="Task audit history panel"`
- Added focused-panel keyboard shortcuts in `tasks.php`:
  - `A` sets `All Events`
  - `S` sets `Status Changes`
- Shortcut behavior is scoped to the focused history shell and ignored for text-entry/control targets (`input`, `textarea`, `select`, `button`, contenteditable).
- Shortcuts integrate with existing persistence behavior:
  - writes row override,
  - updates override summaries,
  - updates global state when remember-mode is enabled.
- Improved focus flow so opening a history panel via either toggle path moves focus into the panel shell, making shortcuts immediately usable.

### Important Files (Keyboard Shortcuts)

- tasks.php
- WORKLOG.md

### Validation (Keyboard Shortcuts)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - panel shell has `tabindex="0"` and expected aria label,
  - pressing `S` activates the status-changes chip,
  - pressing `A` activates the all-events chip,
  - keyboard actions trigger user feedback toast.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 19)

### Scope (Precedence Hint)

- Add compact inline help text in task audit-history panels that explains filter precedence order.

### Key Changes (Precedence Hint)

- Updated `tasks.php` history toolbar markup (server-rendered and JS-rendered) to include a small hint:
  - `Priority: Row > Global > Default`
- Added a hover title (`Filter priority order`) for extra context without adding layout weight.
- Kept the hint as passive explanatory UI so existing chip, summary, global badge, and bulk-action behavior remains unchanged.

### Important Files (Precedence Hint)

- tasks.php
- WORKLOG.md

### Validation (Precedence Hint)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms precedence hint is present with expected text and title attribute:
  - text: `Priority: Row > Global > Default`
  - title: `Filter priority order`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 18)

### Scope (Global Mode Badge)

- Add a compact Global mode status badge in task audit-history toolbars to make remember-mode state immediately visible.

### Key Changes (Global Mode Badge)

- Updated `tasks.php` history toolbar markup (server-rendered and JS-rendered) to include a `Global mode: On/Off` badge beside the summary chip.
- Added centralized badge refresh helpers in `tasks.php`:
  - refresh single shell badge,
  - refresh all shell badges.
- Wired badge refreshes to state transitions that can affect global remember mode:
  - remember checkbox toggle,
  - apply-visible when global mode write is active,
  - bulk-apply undo when global mode is restored,
  - initial shell bind.
- Added visual state styling for quick scanning:
  - Off: neutral gray badge,
  - On: green-highlight badge.

### Important Files (Global Mode Badge)

- tasks.php
- WORKLOG.md

### Validation (Global Mode Badge)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms expected badge transitions:
  - initial: `Global mode: Off`,
  - remember checked: `Global mode: On`,
  - after apply-visible and undo: remains `Global mode: On` when restored state is enabled,
  - remember unchecked: `Global mode: Off`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 17)

### Scope (Override Summary Chip)

- Add a compact live summary indicator in each task audit-history panel showing how many listed rows currently have row-level overrides.

### Key Changes (Override Summary Chip)

- Updated `tasks.php` history toolbar markup (server-rendered and JS-rendered) to include a summary label: `Rows overridden: X / Y`.
- Added centralized summary helpers in `tasks.php` to:
  - count listed task rows,
  - count row overrides from session-backed row preferences,
  - refresh one shell or all shells consistently.
- Wired summary refreshes into all relevant actions:
  - row chip clicks,
  - reset view,
  - apply-to-visible,
  - clear-overrides-visible,
  - undo flows for both bulk actions,
  - initial shell bind.
- Kept summary synchronization consistent for hidden and visible history rows by refreshing all bound shells after bulk mutations.

### Important Files (Override Summary Chip)

- tasks.php
- WORKLOG.md

### Validation (Override Summary Chip)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms summary transitions on current dataset:
  - initial: `Rows overridden: 0 / 2`,
  - after one row override: `Rows overridden: 1 / 2`,
  - after apply visible: `Rows overridden: 2 / 2`,
  - after clear visible: `Rows overridden: 0 / 2`,
  - after undo clear: `Rows overridden: 2 / 2`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 16)

### Scope (Clear Row Overrides)

- Add a companion bulk action to clear row-level audit-history filter overrides across visible task rows.

### Key Changes (Clear Row Overrides)

- Updated `tasks.php` history filter toolbar markup (server-rendered and JS-rendered) to add `Clear row overrides (visible)`.
- Implemented clear-visible handler in `tasks.php`:
  - clears stored row overrides for listed task rows,
  - computes and reports exact cleared override count,
  - refreshes all bound history shells so source/filter chips immediately reflect global/default fallback.
- Added undo support for clear-visible action using existing actionable toast pattern:
  - snapshots prior row override state,
  - restores previous values on Undo,
  - refreshes all bound shells after restore,
  - confirms with `Clear row overrides was undone.` toast.

### Important Files (Clear Row Overrides)

- tasks.php
- WORKLOG.md

### Validation (Clear Row Overrides)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - clear-visible action reports `Cleared 2 row overrides.` on current dataset,
  - without undo, target rows fall back to `Source: Default (All Events)` with status-changes chip inactive,
  - with undo, prior row override state is restored (e.g., `Source: Row (Status Changes)`), and confirmation toast appears.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 15)

### Scope (Undo Last Bulk Apply)

- Add a one-click Undo action for the most recent audit-history bulk apply operation.

### Key Changes (Undo Last Bulk Apply)

- Extended toast behavior in `tasks.php` to support optional inline action buttons.
- Updated bulk apply flow in `tasks.php` to snapshot previous state before apply:
  - per-row stored history filter for all listed task rows,
  - global remember-mode state (enabled/filter) when global mode is being updated.
- Added toast action `Undo` after bulk apply.
- Implemented undo restore logic in `tasks.php`:
  - restores each row override to its prior value (or clears when previously unset),
  - restores global remember-mode state when it was changed by bulk apply,
  - refreshes all bound history shells so chips/source labels reflect restored state immediately,
  - confirms completion with `Bulk apply was undone.` toast.

### Important Files (Undo Last Bulk Apply)

- tasks.php
- WORKLOG.md

### Validation (Undo Last Bulk Apply)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - bulk apply toast shows an actionable Undo button,
  - clicking Undo reverts target rows to prior filter/source state,
  - second row returns to `Source: Default (All Events)` with status-changes chip inactive,
  - toast confirms undo completion.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 14)

### Scope (Bulk View Apply)

- Add a one-click control in task audit history panels to apply the current chip mode to all visible task rows.

### Key Changes (Bulk View Apply)

- Updated `tasks.php` history filter toolbar markup (server-rendered and JS-rendered) to include `Apply this view to visible rows`.
- Implemented bulk apply behavior in `tasks.php`:
  - uses the current active chip (`All Events` or `Status Changes`),
  - writes row-level filter preference for each listed task row,
  - refreshes bound history shells immediately (including hidden rows already initialized),
  - preserves compatibility with global remember mode by syncing global filter when remember is enabled,
  - shows confirmation toast with affected row count.
- Fixed a stale UI edge case discovered during validation where pre-bound hidden shells did not reflect bulk changes until manually reset.

### Important Files (Bulk View Apply)

- tasks.php
- WORKLOG.md

### Validation (Bulk View Apply)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - clicking `Apply this view to visible rows` from one row updates other visible rows,
  - target rows show `Source: Row (Status Changes)` with the status-changes chip active,
  - toast confirms affected row count (`Applied to 2 visible rows.` in current dataset).

## 2026-06-04 - Nutshell Improvements (Task Module Slice 13)

### Scope (Filter Source Visibility)

- Add a compact per-row indicator in task audit history panels to show whether current chip mode is sourced from row override, global remember mode, or default.

### Key Changes (Filter Source Visibility)

- Updated `tasks.php` history filter toolbar markup (server-rendered and JS-rendered) to include a small source indicator label.
- Added source-indicator update logic in `tasks.php` filter binding with consistent states:
  - `Source: Row (...)` after chip clicks (row override),
  - `Source: Global (...)` when global remember mode drives fallback,
  - `Source: Default (...)` when no row/global preference applies.
- Wired source updates across all key interactions:
  - initial panel bind,
  - chip click,
  - remember-toggle changes (when no row override exists),
  - reset-view fallback logic.

### Important Files (Filter Source Visibility)

- tasks.php
- WORKLOG.md

### Validation (Filter Source Visibility)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms expected transitions:
  - first row shows `Source: Row (Status Changes)` after explicit row selection,
  - second row shows `Source: Global (Status Changes)` when inheriting global mode,
  - second row shows `Source: Row (All Events)` after row-level override,
  - reset view restores fallback and shows `Source: Global (All Events)`.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 12)

### Scope (Optional Global History Mode)

- Add an optional global history filter memory mode that can apply one chip view across task rows, while preserving per-row override behavior.

### Key Changes (Optional Global History Mode)

- Updated `tasks.php` history panels (server + async renderer) to include a `Remember for all rows` checkbox in the chip toolbar.
- Added global session storage keys in `tasks.php` to track:
  - global-enabled state,
  - global filter mode (`all` or `status_changes`).
- Implemented precedence logic in `tasks.php` history binding:
  - row-specific preference (if present),
  - otherwise global preference when enabled,
  - otherwise default `All Events`.
- Synchronized `Remember for all rows` checkbox state across open panels.
- Updated `Reset view` behavior to clear only row-specific override and fall back to global/default mode.

### Important Files (Optional Global History Mode)

- tasks.php
- WORKLOG.md

### Validation (Optional Global History Mode)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime validation on `tasks.php` confirms:
  - global remember mode can be enabled,
  - row reset clears local override and re-applies global/default fallback,
  - fallback behavior persists across close/reopen and page reload in the same session.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 11)

### Scope (Per-Task View Reset Control)

- Add a lightweight per-row reset control to clear a task's saved history chip mode and return that row to default view behavior.

### Key Changes (Per-Task View Reset Control)

- Updated `tasks.php` history panel UI (server + async renderer) to add a `Reset view` control beside filter chips.
- Added `clearStoredAuditFilter(...)` helper in `tasks.php` to remove task-specific session preference keys safely.
- Refactored history chip activation flow in `tasks.php` to centralize filter switching and persistence behavior.
- Wired reset action in `tasks.php` to:
  - clear saved filter for that task,
  - switch the panel back to `All Events`,
  - keep default mode active across close/reopen and reload unless user selects a new mode.

### Important Files (Per-Task View Reset Control)

- tasks.php
- WORKLOG.md

### Validation (Per-Task View Reset Control)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime validation on `tasks.php` confirms reset sets `All Events` active and keeps that default active after panel reopen and page reload.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 10)

### Scope (Session-Persisted History View Mode)

- Preserve each task row's expanded-history chip preference (`All Events` vs `Status Changes`) for the current browser session.

### Key Changes (Session-Persisted History View Mode)

- Updated `tasks.php` client logic to persist per-task history filter mode in `sessionStorage` using task-ID-scoped keys.
- Added restore-on-bind behavior so history panels initialize with the saved chip state after:
  - panel reopen,
  - async history re-render,
  - full page reload in the same tab/session.
- Added safe storage guards/fallbacks so private-mode or quota-related storage failures do not break history rendering.

### Important Files (Session-Persisted History View Mode)

- tasks.php
- WORKLOG.md

### Validation (Session-Persisted History View Mode)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime validation on `tasks.php` confirms `Status Changes` selection remains active after panel close/reopen and persists across page reload in the same session.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 9)

### Scope (History Filter Chips)

- Add compact per-task history filter chips so users can switch between all events and status-change-only entries in expanded audit rows.

### Key Changes (History Filter Chips)

- Updated `tasks.php` history panel markup (server + async renderer) to include chips:
  - `All Events`,
  - `Status Changes`.
- Added per-entry diff marker metadata (`data-has-diff`) and empty-state note for status-only views with no matching items.
- Added reusable client-side wiring in `tasks.php` (`wireAuditHistoryFilters`) to:
  - bind chip interactions,
  - apply row visibility filtering,
  - keep chip active styling consistent,
  - auto-bind newly rendered async history content after inline status updates.
- Preserved structured transition fields from `update_task_status.php` history payload so status-only filtering works the same in no-reload flows.

### Important Files (History Filter Chips)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (History Filter Chips)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms:
  - chip controls render in expanded history rows,
  - chip active state toggles correctly,
  - labeled status diffs and tone colors remain intact.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 8)

### Scope (Readable Diff Labels + Visual Tone)

- Improve audit diff readability by converting raw status keys to human labels and adding tone-based color signals for transition type.

### Key Changes (Readable Diff Labels + Visual Tone)

- Updated `tasks.php` server-side history shaping to add structured status transition fields for each task audit item:
  - `status_from`,
  - `status_to`,
  - `status_diff_label` (human-readable, e.g. `In Progress -> Review`),
  - `status_diff_tone` (`progress`, `closed`, `reopened`).
- Added helper logic in `tasks.php` to classify transition tone and apply corresponding render style.
- Updated `tasks.php` history renderers (PHP + JS) to display human-friendly status diff labels and tone-based color styling.
- Updated `update_task_status.php` AJAX history payload generation to include the same structured transition fields, keeping no-reload updates consistent with initial server render.

### Important Files (Readable Diff Labels + Visual Tone)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Readable Diff Labels + Visual Tone)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms expanded history rows show label-based diffs (e.g., `Waiting/Blocked -> Review`) with tone color styling.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 7)

### Scope (Audit History Diff Clarity)

- Make task audit history entries more informative by showing a compact status transition diff for status update events.

### Key Changes (Audit History Diff Clarity)

- Updated `tasks.php` to parse `audit_log.changes` and extract status transitions from JSON payloads (`status.old` -> `status.new`).
- Extended server-rendered expandable history entries in `tasks.php` with a compact diff line:
  - `status: old -> new`.
- Updated client-side history renderer in `tasks.php` so async refreshes include the same diff line after inline status saves.
- Updated `update_task_status.php` history payload generation to include `status_diff` for each returned history entry.

### Important Files (Audit History Diff Clarity)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Audit History Diff Clarity)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms expanded history rows display status transition lines and refresh correctly after inline status updates.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 6)

### Scope (Expandable Task Audit Timeline)

- Add in-row expandable task audit history so users can inspect recent task events without leaving the task list.

### Key Changes (Expandable Task Audit Timeline)

- Updated `tasks.php` to preload and group the latest three `audit_log` events per visible task.
- Added `View last 3 events` toggle under each task's `Recent Audit` preview.
- Added hidden expandable history rows (`task-audit-history-row`) beneath each task row that show:
  - audit summary,
  - timestamp,
  - actor (`user_id`),
  - action,
  - audit status.
- Extended async status-update JS in `tasks.php` to:
  - keep toggle state behavior (`View`/`Hide history`),
  - refresh expanded history content in place after inline status updates,
  - remove paired history rows when filtered-out task rows are removed.
- Updated `update_task_status.php` AJAX response to include `audit_history` (top 3 events) alongside `audit_preview` for in-place UI refresh.

### Important Files (Expandable Task Audit Timeline)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Expandable Task Audit Timeline)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms:
  - history toggle expands/collapses the per-task audit row,
  - inline status save updates status badge, recent audit preview, and expanded history list without page reload,
  - toast confirms save action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 5)

### Scope (Per-Task Audit Visibility)

- Surface recent task audit activity directly in the task list and keep it synced during inline status updates.

### Key Changes (Per-Task Audit Visibility)

- Updated `tasks.php` to load recent audit preview data for visible tasks using a single batched `audit_log` query keyed by task IDs.
- Added a new `Recent Audit` column to the task table showing:
  - latest summary,
  - timestamp,
  - actor (`user_id`) when available,
  - fallback message when no task audit exists.
- Extended async inline status JS in `tasks.php` to refresh the `Recent Audit` cell in place from endpoint JSON after status saves.
- Updated `update_task_status.php` to include `audit_preview` in AJAX responses by fetching the latest task audit row for the updated task.

### Important Files (Per-Task Audit Visibility)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Per-Task Audit Visibility)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime verification on `tasks.php` confirms:
  - `Recent Audit` column renders,
  - inline status save updates both status badge and audit preview in place,
  - toast confirms successful status update.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 4)

### Scope (Async Inline Status UX)

- Remove full-page reload dependency for inline task status changes and provide immediate user feedback.

### Key Changes (Async Inline Status UX)

- Updated `update_task_status.php` to support AJAX/JSON responses when requested:
  - detects XMLHttpRequest or JSON Accept headers,
  - returns structured success/error payloads with HTTP status codes,
  - keeps existing redirect behavior as a non-JavaScript fallback.
- Updated `tasks.php` inline status controls for progressive enhancement:
  - added JS hook classes for inline status forms/buttons/selects,
  - added status badge metadata for in-place badge refresh,
  - added toast notification surface for success/failure feedback.
- Added client-side async submit logic on `tasks.php`:
  - intercepts inline status form submit,
  - posts via `fetch` with same-origin credentials,
  - updates row status badge in place,
  - removes rows that no longer match active status/open filters,
  - shows error/success toast messages.

### Important Files (Async Inline Status UX)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Async Inline Status UX)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime inspection confirms task rows render JS-enhanced inline status controls and status badge metadata.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 3)

### Scope (Inline Status Actions + Traceability)

- Add fast status-change controls directly in the task list while enforcing CSRF validation and writing structured audit entries for traceability.

### Key Changes (Inline Status Actions + Traceability)

- Updated `tasks.php` to render per-row inline status update controls in the Actions column:
  - status dropdown,
  - Save button,
  - CSRF token per form.
- Added filter-preserving return query support so inline updates return users to their active task view/status/assignee context.
- Added new `update_task_status.php` endpoint to process inline updates safely:
  - POST-only guard,
  - CSRF validation,
  - allowed-status validation,
  - task existence check,
  - status update via `update_task_mysql(...)`.
- Added audit logging for inline status updates via `logAuditAction(...)` with old/new status values and success/failed state.

### Important Files (Inline Status Actions + Traceability)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Inline Status Actions + Traceability)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime verification on `tasks.php?view=all` confirms inline status dropdown + Save controls render per row.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 2)

### Scope (Task View Defaults + Persistence)

- Improve daily usability by making task view filters persist across navigation and defaulting signed-in users to a personal work queue.

### Key Changes (Task View Defaults + Persistence)

- Updated `tasks.php` to persist selected task filters in session (`view`, `status`, `assignee`).
- Added My Open default behavior for authenticated users when no explicit filter is provided.
- Added filter reset behavior (`tasks.php?reset_filters=1`) that clears stored filter preferences and returns to defaults.
- Added filter input sanitization against allowed view/status sets before use and persistence.

### Important Files (Task View Defaults + Persistence)

- tasks.php
- WORKLOG.md

### Validation (Task View Defaults + Persistence)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - default view is `My Open Tasks` for signed-in user,
  - reset link clears saved filters,
  - summary cards and filtered table stay consistent with selected filters.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 1)

### Scope (Task Module UX + Reporting Quick Wins)

- Deliver first concrete modernization slice from the task roadmap with lightweight reporting and assignee-aware filtering on the main tasks page.

### Key Changes (Task Module UX + Reporting Quick Wins)

- Updated `tasks.php` with an at-a-glance summary strip showing:
  - visible task count,
  - my open task count,
  - due today count,
  - overdue count.
- Added filter controls on `tasks.php` for:
  - view mode (`All`, `Open`, `My Open`),
  - status,
  - assignee text match.
- Implemented in-page filter application logic that preserves existing add/edit/delete task flows and does not change DB schema.

### Important Files (Task Module UX + Reporting Quick Wins)

- tasks.php
- WORKLOG.md

### Validation (Task Module UX + Reporting Quick Wins)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms new summary cards and filter bar render and interact correctly.

## 2026-06-04 - Admin Sales Integrity Reporting and Bulk Ops Cleanup

### Scope (Admin Visibility + Interaction Safety)

- Add a dedicated integrity audit screen for sales relationship consistency and clean up admin bulk-ops UI/template issues discovered during hardening.

### Key Changes (Admin Visibility + Interaction Safety)

- Added new `admin_integrity_report.php` with cross-module integrity checks and sample rows for:
  - opportunities missing/invalid contact links,
  - tasks referencing missing opportunities,
  - discussion entries referencing missing opportunities,
  - contracts referencing missing contacts/customers.
- Added CSRF-protected, batch-limited one-click safe repair controls on `admin_integrity_report.php` for orphan link cleanup:
  - clear `tasks.opportunity_id` when referenced opportunity is missing,
  - clear `discussion_log.linked_opportunity_id` when referenced opportunity is missing.
- Added guided, preview-first contract customer repair in `admin_integrity_report.php` for orphan `contracts.customer_id` links:
  - requires preview action first,
  - requires explicit typed confirmation (`CONFIRM`) before apply,
  - enforces a preview expiry window before apply can run.
- Added audit trail logging for integrity repairs via existing `logAuditAction(...)` into `audit_log` with action type, batch size, rows changed, and status for each run.
- Normalized contract/customer relationship matching to handle zero-padded customer IDs (e.g., `00003`) versus plain numeric contract references (e.g., `3`) so integrity checks do not produce false orphan-customer positives.
- Extended normalization hardening to all contact-link integrity checks in `admin_integrity_report.php`:
  - opportunities missing contact,
  - contracts missing contact,
  - contracts missing customer.
  These checks now use exact-match OR numeric-equivalent fallback to avoid false positives when IDs differ only by zero-padding format.
- Refactored `admin_integrity_report.php` to use a single shared normalized-ID SQL helper (`normalizedIdExistsClause(...)`) consumed by both report and repair queries, reducing future drift risk between detection and fix paths.
- Extracted reusable admin SQL helpers into new `admin_sql_helper.php` and switched both `admin_integrity_report.php` and `admin_bulk_ops.php` to consume shared functions (`adminTableHasColumn`, `adminOpportunityIdColumn`, `adminNormalizedIdExistsClause`) instead of duplicate local implementations.
- Expanded shared-helper adoption to opportunity-related flows that previously duplicated ID-column/schema checks:
  - `delete_opportunity.php`,
  - `update_opportunity_inline.php`,
  - `pipeline_board.php`,
  - `edit_opportunity.php`.
  These now use the same `adminOpportunityIdColumn`/`adminTableHasColumn` logic for consistent behavior.
- Added CLI smoke test `tests/AdminSqlHelperSmokeTest.php` to regression-check:
  - normalized predicate generation safety/shape,
  - invalid input guardrails (exception paths),
  - DB-backed helper behavior (`adminOpportunityIdColumn`, `adminTableHasColumn`).
- Added one-command wrapper script `scripts/run-admin-sql-smoke.ps1` to run lint + helper smoke checks in a single step for local pre-deploy validation.
- Added GitHub Actions workflow `.github/workflows/admin-sql-smoke.yml` to run the helper smoke test on push/PR in CI mode (`ADMIN_SQL_SMOKE_SKIP_DB=1`) so hosted runners do not require local DB state.
- Hardened wrapper determinism: `scripts/run-admin-sql-smoke.ps1` now supports `-SkipDb` and explicitly sets/restores `ADMIN_SQL_SMOKE_SKIP_DB` each run to avoid terminal-session env leakage.
- Extended CI workflow with a second DB-backed job (`admin-sql-smoke-db`) using a disposable MySQL 8 service container, schema bootstrap, and full smoke-test execution without skip mode.
- Added endpoint-level regression smoke test `tests/OpportunityEndpointHelperUsageSmokeTest.php` to enforce helper adoption in opportunity/admin endpoints and block legacy local-ID-probe drift.
- Added release-note artifact `ADMIN_SQL_HARDENING_RELEASE_NOTES_2026-06-04.md` to isolate this hardening stream for cleaner review in a dirty working tree.
- Added an Admin Dashboard tool link to the new integrity report page.
- Cleaned `admin_bulk_ops.php` template issues:
  - replaced inline CSRF echo usage with `renderCSRFInput()` direct calls,
  - resolved template/JS parser noise in select-all checkbox handlers,
  - updated opportunity edit links in bulk views to `edit_opportunity.php?id=...`.
- Fixed Admin Dashboard runtime error caused by collation mismatch in active-user join logic by switching username/user_id comparison to binary-safe matching in `admin_helper.php`.

### Important Files (Admin Visibility + Interaction Safety)

- admin_integrity_report.php
- admin_dashboard.php
- admin_bulk_ops.php
- admin_helper.php
- WORKLOG.md

### Validation (Admin Visibility + Interaction Safety)

- PHP syntax checks passed for `admin_integrity_report.php`, `admin_dashboard.php`, and `admin_bulk_ops.php`.
- VS Code diagnostics: no errors in modified admin files.
- Runtime verification passed:
  - `admin_dashboard.php` loads without runtime error and shows the new Integrity Report tool link,
  - `admin_integrity_report.php` loads and returns issue counts/tables,
  - one-click repair action verified: discussion orphan-opportunity count reduced from 3 to 0 with success feedback,
  - one-click repair action (`Fix Task Links`) verified post-audit update and created a new `audit_log` entry with `entity_type=integrity` and success status,
  - guided contract-customer repair verified: preview action shows second-step apply form; invalid confirmation is blocked with warning and logged to `audit_log` as failed,
  - normalized orphan-customer logic verified with direct SQL probe (`normalized_orphan_count=0`) and runtime dashboard count now shows `Contracts Missing Customer = 0`.
  - normalized probes for all relevant link checks returned zero (`opps_missing_contact_norm=0`, `contracts_orphan_contact_norm=0`, `contracts_orphan_customer_norm=0`) and runtime integrity page remains clean.
  - helper refactor validated: `php -l` clean, diagnostics clean, runtime page shows all-zero integrity counts with no query regressions.
  - shared-helper extraction validated: syntax/diagnostics clean for helper + consumer pages and runtime loads verified for both `admin_integrity_report.php` and `admin_bulk_ops.php`.
  - additional rollout validated: syntax/diagnostics clean for all updated opportunity files and runtime loads confirmed for `pipeline_board.php`.
  - smoke test validated with `php tests/AdminSqlHelperSmokeTest.php` (PASS).
  - wrapper script validated with `powershell -ExecutionPolicy Bypass -File .\\scripts\\run-admin-sql-smoke.ps1` (PASS).
  - CI-mode path validated locally (`ADMIN_SQL_SMOKE_SKIP_DB=1` and wrapper `-SkipDb`) with passing output.
  - DB-backed local smoke path revalidated after env reset (`Remove-Item Env:ADMIN_SQL_SMOKE_SKIP_DB; php tests/AdminSqlHelperSmokeTest.php`) with PASS.
  - endpoint helper usage smoke test lint + execution validated in both wrapper modes (default and `-SkipDb`) with PASS.
  - `admin_bulk_ops.php` loads and renders all bulk sections.

## 2026-06-04 - Sales Module Interaction Audit and Cross-Flow Fixes

### Scope (Sales/Tasks/Contracts/Discussions Integration)

- Validate and correct interactions between opportunities, contracts, tasks, discussions, and shared layout/security patterns.

### Key Changes (Sales/Tasks/Contracts/Discussions Integration)

- Fixed task-to-opportunity navigation in `tasks.php` to use `edit_opportunity.php?id=...` (was using an unsupported query parameter key).
- Updated `edit_task.php` status options to use the same canonical status vocabulary used by task creation and lists.
- Updated `add_task.php` to persist `priority` and `assigned_to` when provided by UI forms.
- Added CSRF token rendering to the dashboard calendar add-task form in `index.php` and aligned task status filters/colors with canonical statuses.
- Refactored `discussion_logger.php` so it behaves as a safe helper when included by other files and only runs redirecting request logic when directly executed.
- Fixed `opportunities_list.php` to rely on shared layout wrappers and close with `layout_end.php` instead of manual document wrappers.
- Fixed `contracts_list.php` renewal action target to an existing page (`contract_edit.php`) and added missing `layout_end.php` include.
- Updated `edit_opportunity.php` company change flow to relink opportunity contact by selected company without mutating contact master records.
- Updated `add_opportunity.php` to run POST/redirect logic before layout output and added explicit CSRF/auth includes.
- Updated `opportunity_form.php` to forward edit requests (`?id=`) to `edit_opportunity.php` and removed duplicate trailing submit markup.
- Repaired broken `calculateAnnualValue()` JavaScript function wiring in `contract_form.php` so fee/date UI logic continues to execute correctly.
- Hardened opportunity delete flows (`delete_opportunity.php`, `admin_bulk_ops.php`) to run transactional dependent cleanup before deletion: clear `tasks.opportunity_id` and `discussion_log.linked_opportunity_id` references to prevent orphaned cross-module links.

### Important Files (Sales/Tasks/Contracts/Discussions Integration)

- add_opportunity.php
- edit_opportunity.php
- opportunity_form.php
- opportunities_list.php
- contracts_list.php
- add_task.php
- edit_task.php
- tasks.php
- index.php
- discussion_logger.php
- contract_form.php
- delete_opportunity.php
- admin_bulk_ops.php
- WORKLOG.md

### Validation (Sales/Tasks/Contracts/Discussions Integration)

- PHP syntax checks passed for all touched files.
- VS Code diagnostics reported no errors for all touched files.
- Runtime page checks passed for `opportunities_list.php`, `contracts_list.php`, and `tasks.php` after changes.

## 2026-06-04 - Equipment/Inventory/Customer Integrity Hardening (Phase 1)

### Scope (Cross-Module Consistency)

- Remove data drift paths between equipment, inventory stock, and customer-facing equipment views.

### Key Changes (Cross-Module Consistency)

- Refactored `equipment_view.php` delete flow to run POST handling before output and to use transactional component-return logic (restock inventory, delete component rows, then delete equipment) to match list-page behavior.
- Added ownership normalization in `equipment_form.php` so legacy `purchased` values are persisted as canonical `customer-owned` while still recognizing legacy values in the form UI.
- Updated `equipment_list.php` filters to treat `purchased` as customer-owned for consistent exclusion from service/rental tank inventory views.
- Updated `customer_view.php` ownership grouping to include legacy `purchased` values as customer-owned, added a missing `equipment_components` mapping query used by resin part display, and corrected add-equipment links to the equipment form flow.
- Added inventory transaction logging (`inventory_transactions`) for equipment-driven stock mutations in `equipment_form.php`, `equipment_list.php`, and `equipment_view.php` so component consume/return actions are auditable alongside quick inventory updates.
- Extracted shared inventory transaction logic to `inventory_tx_helper.php` and refactored equipment modules to call the shared helper, removing duplicated table/bootstrap and audit write logic.
- Removed remaining pass-through wrapper functions in equipment modules so `equipment_form.php`, `equipment_list.php`, and `equipment_view.php` now call `inventory_tx_helper.php` functions directly.
- Improved `customer_view.php` pool assignment UX feedback to show partial fulfillment warnings and exact resulting assignment counts instead of always showing generic success.

### Important Files (Cross-Module Consistency)

- equipment_view.php
- equipment_form.php
- equipment_list.php
- customer_view.php
- WORKLOG.md

### Validation (Cross-Module Consistency)

- PHP syntax checks passed for all modified files.
- VS Code diagnostics reported no file errors in modified files.
- Executed a controlled end-to-end smoke test for equipment create/update/delete stock effects via shared helper: consumed -1, consumed -1, returned +2 on a test item; `quantity_in_stock` returned to baseline and 3 `inventory_transactions` rows were recorded with ordered before/after quantities.

## 2026-06-04 - Inventory Ledger MySQL Modernization (Phase 1)

### Scope (Inventory Module Reliability)

- Remove fragile CSV/file dependency paths in the active inventory ledger page and standardize on MySQL-backed storage.

### Key Changes (Inventory Module Reliability)

- Updated `inventory_ledger.php` to use MySQL-backed helper functions for ledger entries and serial tracking (`inventory_ledger_entries`, `inventory_serials`) instead of CSV file I/O.
- Added table ensure/compatibility logic that backfills missing columns on pre-existing tables to avoid runtime breakage on mixed schemas.
- Kept existing form workflows intact by preserving call signatures while routing reads/writes through DB-backed functions.
- Merged transaction ledger rows from `inventory_transactions` into the ledger view with a `Source` column (`Manual` vs `Transaction`) and date-desc ordering.
- Added filter bar controls for item, status, client, serial/reference, source type, and date range to make mixed ledger data navigable.
- Moved `layout_start.php` include to render phase so POST handlers and redirects execute before output.

### Important Files (Inventory Module Reliability)

- inventory_ledger.php
- WORKLOG.md

### Validation (Inventory Module Reliability)

- PHP syntax check passed for `inventory_ledger.php`.
- Runtime verification passed: `inventory_ledger.php` loads successfully in browser (no fatal error) and renders ledger/serial forms.
- Runtime verification passed after Phase 2 changes: merged ledger table renders transaction rows and filter controls operate without page errors.

## 2026-06-04 - Relationship Touchpoint Workflow (Phase 1)

### Scope (Bespoke High-Touch CRM)

- Implement a low-user, relationship-first workflow focused on reminders, touchpoints, and customer follow-up consistency.

### Key Changes (Bespoke High-Touch CRM)

- Added migration script `sql/migrations/2026-06-04_relationship_touchpoints_phase1.sql` to introduce relationship fields on `customers` and `contacts`, plus new `customer_touchpoints` history table.
- Extended `customer_view.php` with:
  - schema-safe update handler for relationship fields,
  - touchpoint log form in the customer context,
  - discussion log write-through for each touchpoint,
  - automatic synchronization of customer/contact last-touch and next-touch fields,
  - optional auto-created follow-up task for scheduled next touch dates.
- Extended `discussion.php` with a quick "Log Touchpoint" form and the same relationship-field synchronization logic for contact-centric logging.
- Extended `dashboard.php` with relationship-focused reminder metrics: touches due today, overdue touches, stale relationships, and at-risk customers.
- Added migration-readiness guard on dashboard so relationship widgets show a clear message when schema updates are missing.

### Important Files (Bespoke High-Touch CRM)

- sql/migrations/2026-06-04_relationship_touchpoints_phase1.sql
- customer_view.php
- discussion.php
- dashboard.php
- WORKLOG.md

### Validation (Bespoke High-Touch CRM)

- Pending: run PHP syntax checks and in-app smoke test after migration execution in target environment.

## 2026-05-21 - Env Precedence Correction (Deep Dive)

### Scope (Production Credential Resolution)

- Correct environment loading precedence that could cause production to keep stale Git-tracked credentials.

### Key Changes (Production Credential Resolution)

- Updated `env_loader.php` so server `.env` is authoritative when present.
- `.env.runtime` is now loaded only when `.env` is absent.

### Important Files (Production Credential Resolution)

- env_loader.php
- WORKLOG.md

### Validation (Production Credential Resolution)

- PHP syntax check passed for `env_loader.php`.

## 2026-05-21 - cPanel MySQL Prefix Fallback

### Scope (Production DB Connectivity)

- Reduce GoDaddy/cPanel credential mismatch failures by trying account-prefixed DB user/database names automatically.

### Key Changes (Production DB Connectivity)

- Added candidate connection attempts in `db_mysql.php`: primary credentials first, then cPanel-prefixed user/db fallback (derived from `CPANEL_ACCOUNT`/`CPANEL_DB_PREFIX` or inferred account path).
- Added structured attempt logging to `error_log` for faster diagnosis when all attempts fail.

### Important Files (Production DB Connectivity)

- db_mysql.php
- WORKLOG.md

### Validation (Production DB Connectivity)

- PHP syntax check passed for `db_mysql.php`.

## 2026-05-21 - Daily Call Status Widget (Admin Dashboard)

### Scope (Operations Visibility)

- Add dashboard visibility for daily 10-contact email automation status.

### Key Changes (Operations Visibility)

- Added `getDailyCallStatus()` in `admin_helper.php` to summarize configured recipient, last send time, sent-today count, call-ready-now count, and tracked row count.
- Added Admin Dashboard widgets/cards and a detail section showing daily call automation state and scheduler reference.

### Important Files (Operations Visibility)

- admin_helper.php
- admin_dashboard.php
- WORKLOG.md

### Validation (Operations Visibility)

- PHP syntax checks passed for `admin_helper.php` and `admin_dashboard.php`.

## 2026-05-21 - Runtime Env Secret Scrub

### Scope (Security Hygiene)

- Remove live credentials from git-tracked runtime environment fallback file.

### Key Changes (Security Hygiene)

- Replaced real DB, API, and SMTP values in `.env.runtime` with placeholders.
- Preserved `.env.runtime` structure so deployment fallback remains usable after filling server-side values.

### Important Files (Security Hygiene)

- .env.runtime
- WORKLOG.md

## 2026-05-21 - Git-Tracked Runtime Env Fallback

### Scope (Deployment Reliability)

- Provide a git-deployable environment fallback when host-side `.env` is missing.

### Key Changes (Deployment Reliability)

- Updated `env_loader.php` to load `.env.runtime` when `.env` is not present.
- Added `.env.runtime` with production runtime values for Git-based deployment fallback.

### Important Files (Deployment Reliability)

- env_loader.php
- .env.runtime
- WORKLOG.md

### Validation (Deployment Reliability)

- PHP syntax checks passed for `env_loader.php` and `db_mysql.php`.

## 2026-05-21 - cPanel Deploy .env Preservation

### Scope (Deployment Safety)

- Prevent server-side production environment file loss during git-based cPanel deployments.

### Key Changes (Deployment Safety)

- Updated `.cpanel.yml` rsync command to exclude `.env` while using `--delete`, ensuring server-only production secrets persist across deploys.

### Important Files (Deployment Safety)

- .cpanel.yml
- WORKLOG.md

## 2026-05-21 - Production DB Credential Fallback Fix

### Scope (Deployment Runtime Fix)

- Resolve production login/database outage caused by empty password resolution path.

### Key Changes (Deployment Runtime Fix)

- Updated `db_mysql.php` to support both `PROD_DB_*` and standard `DB_*` environment variable names in production.
- Added fallback logic so a blank `config.local.php` production password can still be filled from environment values.
- Tightened production env completeness check to require a non-empty password before using env-only credentials.

### Important Files (Deployment Runtime Fix)

- db_mysql.php
- WORKLOG.md

### Validation (Deployment Runtime Fix)

- PHP syntax check passed for `db_mysql.php`.

## 2026-05-21 - Login Reliability + Error Visibility Hardening

### Scope (Production Login Blank-Page Triage)

- Eliminate silent auth/login failures on production and ensure errors are captured in project logs.

### Key Changes (Production Login Blank-Page Triage)

- Added login bootstrap logging in `simple_auth/login.php` to ensure `logs/` exists and direct PHP runtime errors to `logs/errors.log`.
- Added defensive `try/catch` around auth initialization in `simple_auth/login.php` with safe 500 response text for bootstrap failures.
- Removed unconditional reliance on `mysqli_stmt::get_result()` in auth/session fetch paths by adding mysqlnd-safe fallback row extraction in `simple_auth/Auth.php` and `simple_auth/SessionDataStore.php`.

### Important Files (Production Login Blank-Page Triage)

- simple_auth/login.php
- simple_auth/Auth.php
- simple_auth/SessionDataStore.php
- WORKLOG.md

### Validation (Production Login Blank-Page Triage)

- PHP syntax checks passed for all modified auth files.

## 2026-05-21 - Admin Access Governance Follow-Up

### Scope (Security Hardening)

- Complete follow-up hardening for internet-exposed auth workflows after adding user administration.

### Key Changes (Security Hardening)

- Restricted the entire Admin sidebar section to admin role users only.
- Added best-effort admin email notification when a new access request is submitted from the public request-access page.
- Removed temporary diagnostics endpoint (`simple_auth/diag.php`) after successful production stabilization.

### Important Files (Security Hardening)

- navbar-sidebar.php
- simple_auth/request_access.php
- simple_auth/diag.php (removed)
- WORKLOG.md

### Validation (Security Hardening)

- PHP syntax checks passed for modified files.

## 2026-05-21 - Access Control Hardening + User Admin Workflow

### Scope (Authentication Security)

- Disable public registration and add an admin-governed access lifecycle for internet-exposed CRM deployment.

### Key Changes (Authentication Security)

- Added admin-only user administration page with CSRF-protected actions for user creation, activation/deactivation, password reset, and registration-request review.
- Added public `simple_auth/request_access.php` form that stores pending access requests in MySQL (`auth_registration_requests`) for admin review.
- Updated login UX to direct non-users to request-access flow instead of self-registration.
- Added admin-only sidebar link to user-access management and added audit logging for admin user-management actions.

### Important Files (Authentication Security)

- simple_auth/admin_users.php
- simple_auth/request_access.php
- simple_auth/login.php
- navbar-sidebar.php
- WORKLOG.md

### Validation (Authentication Security)

- PHP syntax checks passed for modified auth/admin files.

## 2026-05-20 - Hosting Hardening + API Contract Alignment

### Scope (Deployment Blocking Fixes)

- Remove production blockers for GoDaddy/Linux deployment by adding Apache hardening, production-driven auth config, and unified contacts API data source.

### Key Changes (Deployment Blocking Fixes)

- Added `.htaccess` with HTTPS redirect, sensitive-file blocks, and internal path restrictions (`DEPRICATED`, `libraries`, `setup`) for Apache/Linux hosting.
- Updated `simple_auth/config.php` to use environment-driven `APP_BASE_URL` and `AUTH_SESSION_COOKIE_SECURE` values instead of hardcoded localhost defaults.
- Migrated `api/contacts.php` from CSV reads to MySQL reads with optional `q`, `limit`, and `offset` query support while preserving header-only API key auth.
- Updated `.env.example`, `.env.production.template`, and deployment documentation to include new auth env requirements and Apache deployment notes.

### Important Files (Deployment Blocking Fixes)

- .htaccess
- simple_auth/config.php
- api/contacts.php
- .env.example
- .env.production.template
- GODADDY_DEPLOYMENT.md
- WORKLOG.md

### Validation (Deployment Blocking Fixes)

- Diagnostics check returned no errors in updated PHP and markdown files.

## 2026-05-20 - Production Env Template for GoDaddy Cutover

### Scope (Deployment Enablement)

- Create a fill-in production environment template to reduce cutover errors during GoDaddy deployment.

### Key Changes (Deployment Enablement)

- Added `.env.production.template` with production placeholders for DB, API keys, SMTP/Graph transport, and daily call link settings.
- Updated GoDaddy deployment guide to reference `.env.production.template` as the quick-start source.

### Important Files (Deployment Enablement)

- .env.production.template
- GODADDY_DEPLOYMENT.md
- WORKLOG.md

### Validation (Deployment Enablement)

- Diagnostics check returned no errors for updated files.

## 2026-05-20 - GoDaddy Client/Server Deployment Readiness

### Scope (Deployment + Mobile Update Path)

- Prepare CRM for internet-hosted client/server operation on GoDaddy.
- Complete mobile-safe call tracking update flow from daily call email links.

### Key Changes (Deployment + Mobile Update Path)

- Added signed daily call link support configuration for public deployments (`DAILY_CALL_BASE_URL`, `DAILY_CALL_LINK_SECRET`, `DAILY_CALL_LINK_MAX_AGE_SECONDS`).
- Added `daily_call_mark.php` endpoint to validate signed links and mark contacts called through a confirmation POST.
- Added GoDaddy deployment runbook documenting DNS/SSL, env setup, upload steps, and validation workflow.

### Important Files (Deployment + Mobile Update Path)

- daily_call_list_helper.php
- daily_call_mark.php
- .env.example
- GODADDY_DEPLOYMENT.md
- WORKLOG.md

### Validation (Deployment + Mobile Update Path)

- Diagnostics check returned no errors in updated PHP files.

## 2026-05-20 - Contact View Add Log Entry Fix

### Scope (Bug Fix)

- Resolve false failure when adding communication logs from the contact view page.

### Key Changes (Bug Fix)

- Fixed POST routing in `contact_view.php` by introducing a stable hidden form action (`form_action=add_discussion`) for the discussion form.
- Updated server-side discussion detection to accept either submit button name or hidden form action.
- Prevented discussion submissions from being misrouted into the contact update handler when submit button name is absent.

### Important Files (Bug Fix)

- contact_view.php
- WORKLOG.md

### Validation (Bug Fix)

- Diagnostics check for `contact_view.php` returns no errors.

### Follow-Up Hardening (Bug Fix)

- Replaced generic POST failure dead-end with explicit redirect error codes for CSRF, missing fields, prepare/insert/update failures, and unknown form action.
- Added visible page-level error banner mapped from `?error=` and success banners from `?updated=1` / `?log_added=1` to improve operator feedback during save operations.

## 2026-05-20 - Daily Ontario Call List Automation

### Scope (Daily Call Workflow)

- Implement a recurring outreach workflow that emails 10 Ontario contacts with phone numbers each day.
- Track call progress so contacts can be marked called and excluded from future daily lists.

### Key Changes (Daily Call Workflow)

- Added daily call tracking helper with MySQL-backed table bootstrap (`daily_call_tracking`) and selection/marking helpers.
- Added Contact List action to email daily Ontario call list to a target email address.
- Added per-contact "Mark Called" action in Contact List UI.
- Added scheduler-friendly runner script (`daily_call_list_send.php`) for daily automation (CLI or authenticated POST).
- Added `DAILY_CALL_EMAIL_TO` placeholder to environment template.

### Important Files (Daily Call Workflow)

- daily_call_list_helper.php
- contacts_list.php
- daily_call_list_send.php
- .env.example
- WORKLOG.md

### Validation (Daily Call Workflow)

- Diagnostics check returned no errors in all edited files.
- Call tracking table is created automatically on first run.

### Follow-Up Enhancements (Daily Call Workflow)

- Added `Call List Ready` filter in Contact List to show only Ontario contacts with phone numbers not yet marked called.
- Set default daily-call recipient from environment (`DAILY_CALL_EMAIL_TO`) with requested value configured.
- Aligned SMTP send behavior in daily call helper with mass email SMTP policy (host/auth/encryption/from validations).

## 2026-05-20 - Documentation Lint Cleanup (Admin Guide)

### Scope (Documentation Lint Cleanup)

- Clear markdown diagnostics noise in legacy admin guide without large content refactor.

### Key Changes (Documentation Lint Cleanup)

- Added file-level markdownlint suppression directive to `ADMIN_GUIDE.md` for legacy formatting rules currently used across the document.

### Important Files (Documentation Lint Cleanup)

- ADMIN_GUIDE.md
- WORKLOG.md

### Validation (Documentation Lint Cleanup)

- Diagnostics check for `ADMIN_GUIDE.md` returns no errors.

## 2026-05-20 - Documentation Ops Update (Public Endpoint Security)

### Scope (Documentation Ops)

- Document operational handling for newly hardened public endpoints.

### Key Changes (Documentation Ops)

- Added Public Endpoint Security Operations section to admin guide covering API key management, public form anti-abuse controls, and IIS hidden-segment validation.
- Added `API_KEYS` placeholder entry to `.env.example` for consistent deployment configuration.

### Important Files (Documentation Ops)

- ADMIN_GUIDE.md
- .env.example
- WORKLOG.md

### Validation (Documentation Ops)

- Diagnostics check returned no errors in updated documentation/template files.

## 2026-05-20 - Public Endpoint Tightening (API Key + Anti-Abuse + CSRF)

### Scope (Public Endpoint Tightening)

- Tighten intentionally public routes without blindly forcing session auth.

### Key Changes (Public Endpoint Tightening)

- Added API key authentication to legacy contacts API endpoint using `API_KEYS` env policy (header/Bearer).
- Added method enforcement, honeypot check, and per-IP cooldown anti-abuse controls to submit-contact handler.
- Added CSRF verification to submit-contact POST handling.
- Added honeypot hidden field to contact form.
- Expanded IIS hidden segment protections for internal/deprecated paths.

### Important Files (Public Endpoint Tightening)

- api/contacts.php
- submit-contact.php
- contact_form.php
- web.config
- WORKLOG.md

### Validation (Public Endpoint Tightening)

- Diagnostics check returned no errors in edited files.
- Refined scan now shows `submit-contact.php` as intentionally public (`Guard=True, Auth=False`) with anti-abuse + CSRF in place.

## 2026-05-20 - Additional Hardening (Legacy API Write Removal + Deprecated Folder Shield)

### Scope (Additional Hardening)

- Remove remaining unauthenticated write surface in legacy API helper endpoint.
- Prevent accidental web access to deprecated code tree on IIS.

### Key Changes (Additional Hardening)

- Disabled POST write behavior in legacy contacts API helper endpoint and returned explicit 405 response guidance.
- Added IIS request-filter hidden segment rule to block direct access to `DEPRICATED` folder.
- Re-ran refined recursive scan over first-party app files.

### Important Files (Additional Hardening)

- api/contacts.php
- web.config
- WORKLOG.md

### Validation (Additional Hardening)

- Diagnostics check returned no errors in edited files.
- Refined residual scan unchanged on policy/public endpoints (`api.php`, `api/contacts.php`, `submit-contact.php`), now with no direct write route remaining in `api/contacts.php`.

## 2026-05-20 - Residual Closure (Import Preview CSRF)

### Scope (Residual Closure)

- Close the last non-public residual CSRF gap detected by heuristic scan.

### Key Changes (Residual Closure)

- Added CSRF verification for the CSV upload/preview POST branch in import contacts page.
- Added CSRF hidden input to upload form and normalized CSRF render usage in commit preview form.
- Re-ran residual auth/CSRF scan to verify only intentional public endpoints remain.

### Important Files (Residual Closure)

- import_contacts.php
- WORKLOG.md

### Validation (Residual Closure)

- Diagnostics check returned no errors in edited file.
- Residual scan output now shows only `api.php` and `submit-contact.php` (policy-classified public endpoints).

## 2026-05-20 - Security Hardening Final Wave (Inventory + Purchase Orders + Residual Legacy Route)

### Scope (Final Wave)

- Close remaining CSRF gaps in inventory/equipment/purchase-order mutating flows.
- Quarantine residual legacy contact list endpoint artifact.

### Key Changes (Final Wave)

- Added CSRF verification for contact list column-apply POST path.
- Added shared request guard enforcement and CSRF input to equipment list mutating form actions (save/duplicate/delete).
- Added shared request guard enforcement across inventory ledger POST branches and inserted CSRF hidden inputs in all ledger/serial/rfid mutating forms.
- Added shared request guard and CSRF input to purchase-order add/edit/receive/delete flows.
- Removed debug redirect banner noise from purchase order add endpoint.
- Replaced executable legacy enhanced contact list file with an authenticated redirect shim to primary contacts list.

### Important Files (Final Wave)

- contacts_list.php
- equipment_list.php
- inventory_ledger.php
- purchase_order_add.php
- purchase_order_edit.php
- purchase_order_receive.php
- purchase_orders_list.php
- enhanced_contact_list.php
- WORKLOG.md

### Validation (Final Wave)

- Diagnostics check returned no errors in all edited files.
- Residual grep verification confirmed POST handlers now pair with CSRF verification/rendering in targeted files.

### Notes (Final Wave)

- Intentionally public endpoint `submit-contact.php` left unauthenticated by policy; no blind auth gate added.
- Deprecated duplicates under `DEPRICATED/` remain as technical debt and may continue to appear in heuristic scans.

## 2026-05-20 - Security Hardening Incremental Pass (Residual Endpoint Reduction)

### Scope (Incremental)

- Reduce residual endpoint risk after Phase 3 by hardening additional direct-write handlers.

### Key Changes (Incremental)

- Added auth middleware requirement to direct discussion logger execution path.
- Added CSRF verification and token rendering to backorder receive flow.
- Added shared request guard enforcement to contract edit POST flow.
- Added shared request guard enforcement to add discussion handler.
- Added shared request guard enforcement to legacy update contact handler.
- Re-ran residual scan to identify remaining pages for next focused hardening wave.

### Important Files (Incremental)

- discussion_logger.php
- backorders_list.php
- contract_edit.php
- add_discussion.php
- update_contact.php
- WORKLOG.md

### Validation (Incremental)

- Diagnostics check returned no errors in all newly edited files.

### Notes (Incremental)

- Remaining residual list now primarily includes larger module pages (purchase order, inventory ledger, and selected list pages) plus intentional public endpoints.

## 2026-05-20 - Security Hardening Phase 3 (Secrets + Guard Helper + Debug Cleanup)

### Scope (Phase 3)

- Remove committed credential values from fallback configuration.
- Introduce shared request guard helper for auth + POST + CSRF enforcement.
- Remove active debug UI/diagnostic noise from key user-facing pages.

### Key Changes (Phase 3)

- Reworked fallback config to avoid hardcoded plaintext DB secrets and source from env/placeholders.
- Improved DB connection env resolution to use complete env sets and clean fallback behavior.
- Added reusable request guard helper (`request_guard.php`) with HTML and JSON variants.
- Migrated multiple endpoints to the shared guard helper for consistent enforcement.
- Removed debug banner/test marker from task calendar page and removed verbose debug blocks from CSV import preview page.
- Fixed duplicate post-loop increment block in calendar rendering logic.

### Important Files (Phase 3)

- config.local.php
- db_mysql.php
- request_guard.php
- add_tasks.php
- archive_task.php
- calendar_task_ajax.php
- bulk_action.php
- update_opportunity_inline.php
- contact_enrich.php
- import_discussion_log.php
- import_discussion_log_manual.php
- commit_import.php
- index.php
- import_contacts.php

### Validation (Phase 3)

- Diagnostics check returned no errors in all edited files.

### Notes (Phase 3)

- Credential values should still be rotated in the DB and reissued in deployment secrets, even after source cleanup.

## 2026-05-20 - Security Hardening Pass (Auth + CSRF + Endpoint Lockdown)

### Scope (Security Hardening)

- Harden exposed state-changing endpoints that were missing auth and/or CSRF enforcement.
- Restrict diagnostic/data-dump scripts to authenticated sessions.
- Remove sensitive session/cookie debug leakage in import commit flow.

### Key Changes (Security Hardening)

- Added auth + CSRF guards to legacy/utility mutation endpoints (`add_tasks.php`, `archive_task.php`, `calendar_task_ajax.php`, `bulk_action.php`).
- Added auth + CSRF protections to contract regeneration and discussion import execution routes.
- Added auth protection to inline update/enrichment/task-edit handlers that previously relied only on CSRF or no guard.
- Removed session/cookie debug output from `commit_import.php` and required auth there.
- Gated diagnostics scripts (`php_info.php`, `check_db.php`, `_show_tables.php`) behind auth middleware.
- Replaced broken root OAuth helper with an authenticated shim to vendor helper script.

### Important Files (Security Hardening)

- add_tasks.php
- archive_task.php
- calendar_task_ajax.php
- bulk_action.php
- contract_regenerations.php
- update_opportunity_inline.php
- contact_enrich.php
- edit_task.php
- import_discussion_log.php
- import_discussion_log_manual.php
- commit_import.php
- php_info.php
- check_db.php
- _show_tables.php
- get_oauth_token.php
- WORKLOG.md

### Validation (Security Hardening)

- Diagnostics check returned no errors in all edited files.

### Notes (Security Hardening)

- This pass focused on endpoint-level security controls without broad feature refactors.
- A follow-up cleanup pass is still recommended for debug UI remnants in page views and for credential rotation/removal from committed config.

## 2026-05-14 - Contact View Encoding Cleanup + Enrichment Missing-Field Fix

### Scope (Contact View + Enrichment)

- Remove mojibake/corrupt UI glyph strings from contact view.
- Fix enrichment behavior/message when fields are blank placeholders (like dashes) rather than true empty strings.

### Key Changes (Contact View + Enrichment)

- Replaced corrupted symbol strings in contact UI with safe entity-based icons/text.
- Fixed fallback rendering so empty values show a visual dash, not literal `&mdash;` text.
- Added missing-field detection in enrichment endpoint using placeholder-aware checks (`-`, `n/a`, `unknown`, `0000-00-00`, etc.).
- Updated enrichment response messaging to explicitly list missing fields when no candidate data is found.
- Added `missing_fields` to enrichment JSON response for better UI diagnostics.

### Important Files (Contact View + Enrichment)

- CRM/contact_view.php
- CRM/contact_enrich.php

### Validation (Contact View + Enrichment)

- `php -l contact_view.php` passed.
- `php -l contact_enrich.php` passed.

### Notes (Contact View + Enrichment)

- This prevents false “nothing missing” outcomes when stored data uses placeholder strings instead of true null/blank values.

## 2026-04-24 - Ledger Parity Monitoring Page

### Scope (Ledger Parity Monitoring Page)

- Add a parity-check report to compare legacy and canonical ledgers during migration.

### Key Changes (Ledger Parity Monitoring Page)

- Added a dedicated page: `inventory_ledger_parity.php`.
- Added summary counters for legacy/canonical totals and linked canonical source_ref rows.
- Added three parity sections:
  - legacy rows missing canonical records
  - quantity mismatches between legacy and canonical rows
  - canonical orphan rows with `source_ref` not found in legacy table
- Added a `Ledger Parity` navigation button from movement history.

### Important Files (Ledger Parity Monitoring Page)

- CRM/inventory_ledger_parity.php
- inventory_ledger_parity.php
- CRM/inventory_movement_history.php
- inventory_movement_history.php

### Validation (Ledger Parity Monitoring Page)

- Diagnostics check: no errors in CRM and root parity/history/export files.

### Notes (Ledger Parity Monitoring Page)

- This page supports fallback deprecation decisions with objective parity evidence.

## 2026-04-24 - Transaction Ledger Phase 2 Read Cutover (Fallback-Safe)

### Scope (Transaction Ledger Phase 2 Read Cutover)

- Switch movement history and export reads to canonical transactions where available.
- Preserve backward compatibility with legacy movement table during transition.

### Key Changes (Transaction Ledger Phase 2 Read Cutover)

- Added canonical-first read logic in history and export endpoints.
- Added automatic fallback to `inventory_movements` when canonical table is missing or empty.
- Normalized canonical fields to current UI/export schema so pages remain unchanged.
- Preserved sorting and date/item filters across both sources.

### Important Files (Transaction Ledger Phase 2 Read Cutover)

- CRM/inventory_movement_history.php
- CRM/inventory_movement_export.php
- inventory_movement_history.php
- inventory_movement_export.php

### Validation (Transaction Ledger Phase 2 Read Cutover)

- Diagnostics check: no errors in CRM and root movement history/export files.

### Notes (Transaction Ledger Phase 2 Read Cutover)

- This allows incremental migration and parity checks before full legacy deprecation.

## 2026-04-24 - Transaction Ledger Phase 1 Dual-Write

### Scope (Transaction Ledger Phase 1 Dual-Write)

- Implement canonical transaction table and dual-write in quick inventory updates.
- Preserve existing movement logging and UI behavior.

### Key Changes (Transaction Ledger Phase 1 Dual-Write)

- Added `inventory_transactions` table bootstrap in quick update path.
- Added canonical transaction insert on normal adjustments (`inc`, `dec`, `set`).
- Added canonical reversal insert on undo with parent transaction lookup by source reference.
- Captured actor/session/network context hashes for future monitoring and AI scoring.
- Kept existing `inventory_movements` writes and notices unchanged for compatibility.

### Important Files (Transaction Ledger Phase 1 Dual-Write)

- CRM/inventory_quick_update.php
- inventory_quick_update.php

### Validation (Transaction Ledger Phase 1 Dual-Write)

- Diagnostics check: no errors in both CRM and root quick update files.

### Notes (Transaction Ledger Phase 1 Dual-Write)

- This is Phase 1 foundation; read paths still use `inventory_movements` until cutover.

## 2026-04-24 - Transaction Ledger V2 Design Record

### Scope (Transaction Ledger V2 Design Record)

- Define canonical transactional ledger model and long-horizon collection, storage, monitoring, and AI strategy.
- Define a canonical transactional ledger model for inventory adjustments and reversals.
- Document collection, archive, monitoring, learning, and AI enhancement strategy.

### Key Changes (Transaction Ledger V2 Design Record)

- Added deep-dive architecture document covering:
  - what to collect (transaction facts, context, actor/session, integrity, AI feedback)
  - how to store (append-only ledger, read models, archive)
  - what it means over 3/6/12 month horizons
  - AI staged rollout and governance
- Added a dedicated design document with:
  - Proposed inventory_transactions schema.
  - Reason code taxonomy and data capture standards.
  - Archive/retention approach.
  - Monitoring and KPI recommendations.
  - AI-assisted anomaly and recommendation strategy.
  - Phased implementation and starter SQL.

### Important Files (Transaction Ledger V2 Design Record)

- TRANSACTION_LEDGER_DEEP_DIVE.md
- CRM/TRANSACTION_LEDGER_DEEP_DIVE.md
- TRANSACTION_LEDGER_V2_PLAN.md
- CRM/TRANSACTION_LEDGER_V2_PLAN.md

### Validation (Transaction Ledger V2 Design Record)

- Confirmed deep-dive and design documents exist in root and CRM trees.

### Notes (Transaction Ledger V2 Design Record)

-- This entry is architectural guidance and design blueprint; does not alter runtime behavior yet.

## 2026-04-24 - Auth, Supplier Master, Inventory UX, Audit Trail

### Scope (Auth, Supplier Master, Inventory UX, Audit Trail)

- Authentication/session reliability improvements and routing cleanup.
- Supplier master data flow (source-of-truth supplier directory and cross-page integration).
- Inventory list UX modernization and operational safety enhancements.
- Quantity movement auditing, undo, history, export, filtering, and sorting.

### Key Changes (Auth, Supplier Master, Inventory UX, Audit Trail)

- Improved auth behavior to reduce unexpected sign-outs and reauth friction.
- Fixed path/routing issues in duplicated root/CRM structure.
- Established supplier master workflow with unique alphanumeric supplier IDs.
- Reworked inventory list to compact table UX with search/filter/sort/pagination.
- Added quick quantity actions (+/-/Set) with safety controls:
  - Large-jump confirmation.
  - Required reason for large Set changes.
  - One-click undo after update.
- Added inventory movement logging with metadata (old/new/delta/mode/reason/user/time).
- Added movement history page and CSV export.
- Added movement history date-range filters and all-column sorting.
- Added broader inventory-list sorting including supplier and operational headers.
- Resolved collation mismatch in movement join queries.

### Important Files (Auth, Supplier Master, Inventory UX, Audit Trail)

- Supplier: `supplier_directory.php`, `inventory_add.php`, `inventory_edit.php`, `purchase_order_add.php`
- Inventory list/updates: `inventory_list.php`, `inventory_quick_update.php`, `inventory_export.php`
- Movement history/export: `inventory_movement_history.php`, `inventory_movement_export.php`
- Navigation: `navbar-sidebar.php`
- Auth stack touchpoints: middleware/login/session flow files under `simple_auth/`

### Validation (Auth, Supplier Master, Inventory UX, Audit Trail)

- Repeated diagnostics checks reported no errors after patch batches.
- Sorting/filter/export consistency aligned across list/history/export endpoints.
- Undo + reason-required behavior verified through implemented flow and notices.

### Notes (Auth, Supplier Master, Inventory UX, Audit Trail)

- This repository contains mirrored files in root and `CRM/`; changes should continue to be applied/synced in both trees.

## 2026-04-30 - Opportunity/Contact Communication Log Refactor

### Scope

- Merge duplicate discussion log forms in contact view.
- Ensure opportunity dropdown is correctly populated for the current contact.
- Clean up UI to prevent duplicate or missing elements in communication logging.
- Update lessons_learned.md with new best practices for communication log features.

### Key Changes

- Removed duplicate discussion log form from the Discussions accordion in contact_view.php.
- Defined $contactOpportunities for correct dropdown population.
- Updated lessons_learned.md with guidance on merging forms, dropdown population, and UI validation.

### Important Files

- contact_view.php
- /memories/lessons_learned.md

### Validation

- UI tested: Only one discussion log form present, dropdown lists correct opportunities, no duplicate discussions.
- Lessons learned and documentation updated.

## 2026-04-30 Opportunity Description Field

- Added `description` field to `opportunity_schema.php` and MySQL schema (migration: sql/2026-04-30_add_opportunity_description.sql)
- Updated `opportunity_form.php` to render description as textarea
- Updated `opportunities_list.php` to display description column
- Next: Run migration to update DB

## 2026-05-12 CRM Enhancement Sprint

### Admin & Navigation
- Added Admin section to sidebar (`navbar-sidebar.php`) with 6 links: Dashboard, Advanced Search, Bulk Ops, Reports, Contact Timeline, Deduplicate
- Fixed blank admin pages (root cause 1): `requireAdmin()` was checking `$_SESSION['logged_in']` — changed to `$_SESSION['user_id']` to match `simple_auth/Auth.php`
- Fixed blank admin pages (root cause 2): Removed duplicate `<div class="main-content">` / `<div class="content-container">` wrappers from all 5 admin pages — `navbar-sidebar.php` already provides both
- `admin_timeline.php`: shows search form (name/company/email) when no `?id=` is provided

### Notification Badges
- Added `$_notif_overdue_tasks` (tasks past due_date, not completed/archived) and `$_notif_expiring_contracts` (active contracts ending within 30 days) queries to navbar-sidebar.php
- Red badge on Tasks nav item; amber badge on Contracts nav item

### Forecast Dashboard
- `forecast_calc.php`: added `name`, `expected_close`, `days_to_close` to each result row
- `forecast_dashboard.php`: new "Closing in 30 Days" (amber) and "Overdue Close Date" (red) metric cards; detail table sorted by close date with colour-coded Days Out column

### Duplicate Contact Merge
- Created `admin_deduplicate.php`: groups contacts by duplicate email, radio-select keep/discard, re-points tasks/opportunities/discussion_log/audit_log before deleting discarded contact; CSRF-protected; logs `merge_contact` audit action

### Bulk Operations
- `admin_bulk_ops.php`: added bulk delete and bulk update sections for Opportunities and Tasks (in addition to existing Contacts sections)
- Tasks use `id varchar(64)` — bind type 's' throughout
- Schema vars: `$opp_schema = ['stage','probability']`, `$task_schema = ['status','priority','assigned_to']`

### contracts_list.php Cleanup
- Removed 50-line tank_size retry/opcache workaround (was a transient issue; `SELECT tank_size FROM contracts` confirmed working)
- Removed stale `opcache_reset()` calls

### REST API
- Created `api.php`: read-only, authenticated via `API_KEYS` in `.env` (Bearer token or `?api_key=` param)
- Endpoints: `/contacts`, `/contacts/{id}`, `/opportunities`, `/opportunities/{id}`, `/tasks`, `/tasks/{id}`, `/contracts`, `/contracts/{id}`
- Supports `?q=`, `?stage=`, `?status=`, `?limit=`, `?offset=` query params; returns `{total, limit, offset, data}`
- API key added to `.env`; tested live — 293 contacts returned correctly

### Mass Email Segment Filters
- `mass_email.php`: added province/status/tags filter panel above recipient list; filters apply client-side (JS) for instant preview plus server-side for send

### Mobile Responsive Layout
- `layout_start.php`: added hamburger `<button id="sidebarToggle">` in top bar for screens ≤768px
- `layout_start.php` / `layout_end.php`: JS toggles `.open` on `#sidebar` and `.active` on `#sidebarOverlay`; CSS already defined these classes in `css/modern-sidebar.css`

### Admin Reports Expansion
- `admin_reports.php`: added "Revenue" report (monthly fee by month/status, total ARR) and "Pipeline" report (opportunities by stage with sum and count)
- New report buttons added to selector grid

### Customer Portal
- Created `customer_portal.php`: session-authenticated, shows customer's active contracts, tank sizes, end dates, and delivery history linked by `customer_id`

## 2026-05-12 Security Hardening Follow-up

### API Authentication Hardening
- Updated `api.php` to enforce **header-only** authentication:
  - `X-API-Key: <key>`
  - `Authorization: Bearer <key>`
- Disabled query-string key auth (`?api_key=`) to prevent credential leakage in logs/history.
- Updated API root metadata (`auth` field) to match header-only behavior.
- Validation run:
  - Header key returns `200`
  - Query-string key returns `401`

### Secret Rotation / Scrubbing
- Rotated `API_KEYS` value in `.env`.
- Cleared exposed `.env` secrets:
  - `OPENAI_API_KEY`
  - `SMTP_PASSWORD`
- Confirmed old API key is invalid after rotation.

### CSRF Regression Fix
- Restored CSRF validation in the general contact update POST path in `contact_view.php`.
- All state-changing POST paths on that page now validate `verifyCSRFToken()` before DB writes.

### Notes / Corrections
- Prior worklog line stated API supported query-param auth; final state is header-only.
- Sidebar mobile toggle was already implemented in `js/modern-ui.js`; final change kept CSS visibility fix and removed duplicate toggle script additions.
