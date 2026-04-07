# Fake Review Detection System

A production-ready, full-stack machine learning application to detect fake reviews.
Built with Flask, Vanilla JS, and **manually implemented ML algorithms** (Logistic Regression, SVM, Decision Tree) without using sklearn for training.

## Features
- **Manual ML Implementation**: Core algorithms built from scratch using NumPy.
- **Ensemble Voting**: Combines predictions from all 3 models for better accuracy.
- **Interactive UI**: Real-time prediction and confidence scoring.
- **Admin Dashboard**: Upload datasets, retrain models, and view confusion matrices.
- **REST API**: endpoints for prediction and training.

## Project Structure
```text
/project_root
    /app
        /models         # Saved pickle models and datasets
        /ml             # Manual ML algorithms (No sklearn)
        /preprocessing  # Text cleaning and Feature Engineering
        routes.py       # API and View routes
    /static             # CSS and JS
    /templates          # HTML Templates
    config.py           # App configuration
    run.py              # Entry point
    Dockerfile          # Docker deployment
```

## Setup Instructions

### 1. Local Installation
1.  **Clone/Download** the repository.
2.  **Install Dependencies**:
    ```bash
    pip install -r requirements.txt
    ```
3.  **Run the Application**:
    ```bash
    python run.py
    ```
4.  Open browser at `http://localhost:5000`.

### 2. Initial Usage
1.  Go to **Admin Dashboard**.
2.  Upload the provided `sample_data.csv` (or your own dataset).
    - Format: CSV with `text` and `label` columns.
    - `0` = Genuine, `1` = Fake.
3.  Click **Start Training**.
4.  Once trained, go to **Prediction** page to test reviews.

### 3. Docker Deployment
1.  **Build Image**:
    ```bash
    docker build -t fake-review-detector .
    ```
2.  **Run Container**:
    ```bash
    docker run -p 5000:5000 fake-review-detector
    ```

### 4. Production (Gunicorn)
To run without Docker in a production environment:
```bash
gunicorn -w 4 -b 0.0.0.0:8000 run:app
```

## Manual Algorithms Setup
- **Logistic Regression**: Implemented with Gradient Descent and Sigmoid activation.
- **Linear SVM**: Implemented with Sub-gradient Descent and Hinge Loss.
- **Decision Tree**: Implemented with Recursive Splitting and Gini Impurity.
- **TF-IDF**: Manual calculation of Term Frequency and Inverse Document Frequency.

## API Endpoints
- `POST /api/predict`: `{"review": "text", "algorithm": "ensemble"}`
- `POST /api/train`: Triggers retraining.
- `POST /api/upload`: Upload CSV dataset.

## License
MIT
