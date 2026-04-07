## Integrated ArtStore + Review System

This folder is a clean, self-contained copy of the **ArtStore web app** integrated with the **Fake Review Detection** (Flask) API, using the **same MySQL database** (`artstore`).

### Folder structure

- `web/` - ArtStore (PHP) + integrated review system (actions, includes, admin pages)
- `ml_api/` - Flask API used by `web/config/review_api.php` (default: `http://localhost:5000`)

### Database

- **DB name**: `artstore`
- **PHP DB config**: `web/config/db.php`
- **Schema**: `web/setup.sql`

### Run (XAMPP)

1. Put `integrated_artstore_review_system/web/` under your Apache root (or keep it where it is and browse via your XAMPP document root).
2. Import the schema:
   - Use `web/setup.sql` (creates database + tables + sample data).
3. Start the ML API:
   - From `ml_api/`:
     - `pip install -r requirements.txt`
     - `python run.py`
   - API should be available at `http://localhost:5000`

### Notes

- The ML API model `.pkl` files are included under `ml_api/app/models/` for inference.
- Large training datasets were removed from this integrated folder to keep it lightweight.

