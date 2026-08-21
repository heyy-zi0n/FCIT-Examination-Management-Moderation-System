# Skill: Tailwind UI/UX Engineering

## Purpose

Create a consistent, professional examination-management interface.

The system should look like one product, not several unrelated PHP pages.

---

## Design Principles

Use:

- clean layouts
- clear hierarchy
- consistent spacing
- responsive design
- accessible controls
- meaningful status indicators
- clear feedback

Avoid unnecessary visual complexity.

---

## Tailwind

Use Tailwind consistently.

Do not introduce another CSS framework.

Reusable UI patterns should be standardized.

---

## Dashboard Structure

Each role should have:

- sidebar
- top navigation
- breadcrumb/context
- page title
- content area
- notifications
- responsive mobile behavior

---

## Status Indicators

Use consistent labels for:

    Draft
    Submitted
    Returned
    Moderated
    Approved
    Locked
    Queued
    Printed

Status must be understandable without inspecting code.

---

## Forms

Forms must:

- have labels
- validate input
- show errors clearly
- preserve valid entered data
- prevent accidental duplicate submissions
- display loading state when appropriate

---

## Tables

Tables must support:

- readable columns
- responsive behavior
- empty states
- loading states
- pagination where needed
- actions appropriate to the user's role

---

## Document Viewer

Document preview should provide:

- clear document title
- version
- paper information
- security/watermark indication where required
- loading state
- conversion failure state
- unauthorized state

Do not expose raw storage paths.

---

## No Placeholder Modules

Required pages must not display:

    Module Under Development
    Coming Soon

unless the project specification explicitly defines the module as out of scope.

---

## Error States

Every major operation needs a meaningful error state.

Examples:

    File missing
    File corrupted
    Preview unavailable
    Unauthorized
    Invalid submission
    Schedule unavailable
    Printing unavailable

Errors should explain what the user can do next without exposing internals.

---

## Responsive Testing

Test:

- desktop
- tablet
- mobile

The application must remain usable at common viewport sizes.

---

## Completion Standard

A UI page is complete only after:

- visual inspection
- browser interaction
- responsive check
- success state
- empty state
- error state
- unauthorized state where applicable