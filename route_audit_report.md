# Route Audit Report - 2026-04-18
**POC**: AdaL
**Status**: PENDING FIX

## Identified Exception Risks

### 1. Missing View: `diagrams.create`
- **Location**: `web.php:18`
- **Risk**: `InvalidArgumentException: View [diagrams.create] not found.`
- **Condition**: Any user navigating to `/diagrams/create`.
- **Status**: Fixed in this turn by creating `resources/views/diagrams/create.blade.php`.

### 2. Model Binding Risk: `diagrams.show`
- **Location**: `web.php:19`
- **Risk**: `ModelNotFoundException`
- **Logic**: `fn($d) => view('diagrams.editor', ['diagram' => $d])`
- **Issue**: The route does not use explicit Route Model Binding in the closure, nor does it constrain the ULID. If an invalid ULID is provided, the controller or view might crash if it assumes the model exists.
- **Recommended Fix**: Use `Diagram $diagram` type-hinting or `findOrFail` if resolved manually.

### 3. API Model Exposure
- **Location**: `api.php:14`
- **Risk**: `404` without context.
- **Issue**: `apiResource` for diagrams doesn't explicitly handle tenant isolation if the `Diagram` model isn't globally scoped or if the request is for a diagram the user doesn't own.
- **Recommended Fix**: Ensure `DiagramController` uses `auth()->user()->diagrams()` to scope all queries.
