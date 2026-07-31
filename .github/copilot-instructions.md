# Copilot Instructions for AI Coding Agents

## Project Overview
This is a PHP-based web application for managing ID cards, attendance, contractors, sanctions, and plant displays. The codebase is organized into MVC-like components, with controllers in `src/Controllers/`, templates in `templates/`, and public assets in `public/`.

## Architecture & Data Flow
- **Controllers** (`src/Controllers/`): Handle business logic and route requests. Each major feature (Attendance, Auth, Contractor, Dashboard, PlantDisplay, Sanction, Setting) has its own controller.
- **Templates** (`templates/`): PHP files for rendering views. Subfolders group related templates (e.g., `contractors/`, `sanctions/`, `settings/`).
- **Public** (`public/`): Entry point (`index.php`), QR code generation (`qr_generator.php`), and static assets (`assets/`). File uploads are stored in `public/uploads/`.
- **Config** (`config/database.php`): Database connection settings.
- **Database** (`database/`): SQL schema and migration scripts.

## Developer Workflows
- **No build step required**; PHP files are served directly.
- **Database setup**: Use SQL scripts in `database/` to initialize and seed the database. Example:
  - `schema.sql` for initial schema
  - `seeder.sql` for test data
  - Run these scripts using your preferred MySQL client.
- **Debugging**: Use `var_dump`, `error_log`, or Xdebug for PHP debugging. Errors are not handled by a custom framework.
- **Static assets**: Place CSS/JS/images in `public/assets/`.

## Project-Specific Conventions
- **Controllers**: Each controller is named after its domain (e.g., `ContractorController.php`). Methods typically correspond to actions (list, form, import, print, etc.).
- **Templates**: Use PHP for rendering. Layouts are in `templates/layout.php`. Views are grouped by feature.
- **Uploads**: Photos and settings are stored in `public/uploads/photos/` and `public/uploads/settings/`.
- **No autoloading**: Files are included manually; check entry points for `require`/`include` statements.
- **Minimal external dependencies**: Most logic is custom PHP; QR code generation may use a library in `qr_generator.php`.

## Integration Points
- **Database**: MySQL, configured in `config/database.php`.
- **QR Code**: Generated via `public/qr_generator.php`.
- **File Uploads**: Handled via forms, stored in `public/uploads/`.

## Examples
- To add a new feature, create a controller in `src/Controllers/`, templates in `templates/[feature]/`, and update routing in `public/index.php`.
- To update the database, modify SQL scripts in `database/` and apply via MySQL.

## Key Files & Directories
- `src/Controllers/` — Business logic
- `templates/` — Views
- `public/index.php` — Main entry point
- `config/database.php` — DB config
- `database/` — SQL scripts
- `public/assets/` — Static files

---
For questions or unclear conventions, ask for clarification or examples from the user.
