from flask import Blueprint, render_template, request, jsonify, current_app
import os
import pandas as pd
import numpy as np
import time

from app.preprocessing.cleaner import preprocess_pipeline
from app.preprocessing.features import ManualTFIDF, extract_meta_features
from app.ml.logistic_regression import LogisticRegressionManual
from app.ml.svm import LinearSVMManual
from app.ml.decision_tree import DecisionTreeManual
from app.ml.ensemble import EnsembleVoteClassifier
from app.ml.metrics import accuracy_score, confusion_matrix
from app.utils.helpers import load_dataset, save_model, load_model
from app.utils.helpers import load_dataset, save_model, load_model
from app.utils.logger import setup_logger
from app.utils.scraper import Scraper

main = Blueprint('main', __name__)
logger = setup_logger('app.routes')

# Global variables to hold active models (validation purpose mainly)
# In production, cache or load per request if small, or keep in memory
current_models = {}
tfidf_vectorizer = None

def init_globals():
    global tfidf_vectorizer
    # Try to load existing vectorizer
    tfidf_vectorizer = load_model('tfidf.pkl')
    if not tfidf_vectorizer:
        logger.info("No TF-IDF model found. Please train first.")

init_globals()

@main.route('/')
def index():
    return render_template('index.html')

@main.route('/dashboard')
def dashboard():
    return render_template('dashboard.html')

@main.route('/api/predict', methods=['POST'])
def predict():
    global tfidf_vectorizer
    data = request.json
    text = data.get('review', '')
    algo = data.get('algorithm', 'ensemble')
    
    if not text:
        return jsonify({'error': 'No text provided'}), 400
        
    if not tfidf_vectorizer:
         return jsonify({'error': 'System not trained yet. Go to Dashboard.'}), 400

    # 1. Preprocess
    start_time = time.time()
    processed_text = preprocess_pipeline(text)
    
    # 2. Extract Features
    # TF-IDF
    tfidf_features = tfidf_vectorizer.transform([processed_text])
    
    # Meta features
    meta_features = np.array([extract_meta_features(text)])
    
    # Combine (Just using TF-IDF for simplicity in manual model, or we can stack)
    # For this implementation, let's stick to TF-IDF features for the ML models to keep dimensions managed manually
    X_input = tfidf_features
    
    # 3. Load Model
    model_name = f"{algo}.pkl"
    model = load_model(model_name)
    
    if not model:
        return jsonify({'error': f'Model {algo} not found.'}), 404
        
    # 4. Predict
    try:
        prediction = model.predict(X_input)[0]
        # For manual scalar return, handle it
        if isinstance(prediction, np.ndarray):
            prediction = prediction.item()
            
        result = "Fake" if prediction == 1 else "Genuine"
        
        # Confidence and Recommendation
        confidence_val = 0.5
        if hasattr(model, 'predict_proba'):
            # Models return prob of class 1 (Fake) or just class 1 prob
            # LogisticRegression: returns [prob] or just scalar prob
            # SVM: returns [prob]
            # DT: returns [prob]
            raw_prob = model.predict_proba(X_input)
            if isinstance(raw_prob, (list, np.ndarray)):
                 raw_prob = raw_prob[0]
            
            # If our models return prob of being 1 (Fake)
            fake_prob = float(raw_prob)
            
            # If result is Genuine (0), confidence is 1 - fake_prob
            if result == "Genuine":
                confidence_val = 1.0 - fake_prob
            else:
                confidence_val = fake_prob
                
        confidence_percent = round(confidence_val * 100, 2)
        
        # Recommendation Logic
        # If Genuine and high confidence -> Buy
        # If Fake -> Don't Buy
        recommendation = "Uncertain"
        if result == "Fake":
            if confidence_percent > 70:
                recommendation = "High Risk: Likely Fake. Avoid purchase."
            else:
                recommendation = "Caution: Potential Fake anomalies detected."
        else:
            if confidence_percent > 70:
                recommendation = "Safe: Reviews appear Genuine."
            else:
                recommendation = "Moderate Risk: verify other sources."

        latency = time.time() - start_time
        
        return jsonify({
            'result': result,
            'confidence': f"{confidence_percent}%",
            'recommendation': recommendation,
            'latency': f"{latency:.4f}s"
        })
    except Exception as e:
        logger.error(f"Prediction error: {e}")
        return jsonify({'error': str(e)}), 500

@main.route('/api/analyze_url', methods=['POST'])
def analyze_url():
    global tfidf_vectorizer
    data = request.json
    url = data.get('url')
    algo = data.get('algorithm', 'ensemble')
    
    if not url:
        return jsonify({'error': 'No URL provided'}), 400
        
    if not tfidf_vectorizer:
         return jsonify({'error': 'System not trained yet.'}), 400
         
    # 1. Scrape
    scraper = Scraper()
    reviews = scraper.extract_reviews(url)
    
    if not reviews:
        return jsonify({'error': 'No reviews found at URL or scraper blocked.'}), 404
        
    # 2. Analyze
    model_name = f"{algo}.pkl"
    model = load_model(model_name)
    if not model:
        return jsonify({'error': f'Model {algo} not found.'}), 404
        
    results = []
    fake_count = 0
    genuine_count = 0
    
    for text in reviews:
        processed_text = preprocess_pipeline(text)
        tfidf_features = tfidf_vectorizer.transform([processed_text])
        X_input = tfidf_features
        
        prediction = model.predict(X_input)[0]
        if isinstance(prediction, np.ndarray): prediction = prediction.item()
        
        is_fake = (prediction == 1)
        if is_fake:
            fake_count += 1
            res_str = "Fake"
        else:
            genuine_count += 1
            res_str = "Genuine"
            
        # Confidence
        conf = 0.5
        if hasattr(model, 'predict_proba'):
             raw = model.predict_proba(X_input)
             if isinstance(raw, (list, np.ndarray)): raw = raw[0]
             float_prob = float(raw)
             conf = float_prob if is_fake else (1.0 - float_prob)
             
        results.append({
            'text': text[:100] + "...",
            'result': res_str,
            'confidence': f"{round(conf * 100, 1)}%"
        })
        
    total = len(reviews)
    fake_metrics = round((fake_count / total) * 100, 1)
    
    summary_rec = "Unknown"
    if fake_metrics > 50:
        summary_rec = "High Risk: Majority of displayed reviews appear suspicious."
    elif fake_metrics > 20:
        summary_rec = "Caution: Significant number of fake reviews detected."
    else:
        summary_rec = "Low Risk: Reviews appear mostly genuine."
        
    return jsonify({
        'total_reviews': total,
        'fake_percentage': f"{fake_metrics}%",
        'summary_recommendation': summary_rec,
        'reviews': results[:20] # Return top 20
    })

@main.route('/api/train', methods=['POST'])
def train():
    global tfidf_vectorizer
    
    # 1. Check for dataset
    dataset_path = os.path.join(current_app.config['UPLOAD_FOLDER'], 'dataset.csv')
    if not os.path.exists(dataset_path):
        return jsonify({'error': 'No dataset found. Upload one first.'}), 400
        
    try:
        df = load_dataset(dataset_path)
        
        # Preprocessing (Batch)
        logger.info("Starting preprocessing...")
        df['processed'] = df['text'].apply(preprocess_pipeline)
        
        # Feature Extraction
        logger.info("Fitting TF-IDF...")
        tfidf_vectorizer = ManualTFIDF(max_features=500) # Keep small for speed in manual
        X = tfidf_vectorizer.fit_transform(df['processed'].tolist())
        y = df['label'].values
        
        # Save vectorizer
        save_model(tfidf_vectorizer, 'tfidf.pkl')
        
        # Train Models
        metrics = {}
        
        # Logistic Regression
        logger.info("Training LR...")
        lr = LogisticRegressionManual(num_iterations=500)
        lr.fit(X, y)
        save_model(lr, 'logistic_regression.pkl')
        metrics['logistic_regression'] = evaluate_model(lr, X, y)
        
        # SVM
        logger.info("Training SVM...")
        svm = LinearSVMManual(num_iterations=500)
        svm.fit(X, y)
        save_model(svm, 'linear_svm.pkl')
        metrics['linear_svm'] = evaluate_model(svm, X, y)
        
        # Decision Tree
        logger.info("Training DT...")
        dt = DecisionTreeManual(max_depth=10) # Limit depth
        dt.fit(X, y)
        save_model(dt, 'decision_tree.pkl')
        metrics['decision_tree'] = evaluate_model(dt, X, y)
        
        # Ensemble (Just save the concept, we re-load them for prediction)
        # We don't pickle the ensemble class itself if it just imports others, 
        # but for consistency let's save a wrapper if needed. 
        # Actually, prediction logic re-loads individual pickles.
        # Let's just evaluate ensemble here.
        ensemble = EnsembleVoteClassifier([lr, svm, dt])
        save_model(ensemble, 'ensemble.pkl')
        metrics['ensemble'] = evaluate_model(ensemble, X, y)
        
        return jsonify({'status': 'Training complete', 'metrics': metrics})
        
    except Exception as e:
        logger.error(f"Training error: {e}")
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

def evaluate_model(model, X, y):
    preds = model.predict(X)
    acc = accuracy_score(y, preds)
    cm = confusion_matrix(y, preds).tolist()
    return {'accuracy': acc, 'confusion_matrix': cm}

@main.route('/api/upload', methods=['POST'])
def upload():
    if 'file' not in request.files:
        return jsonify({'error': 'No file part'}), 400
    
    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'No selected file'}), 400
        
    if file and file.filename.endswith('.csv'):
        filename = 'dataset.csv'
        file.save(os.path.join(current_app.config['UPLOAD_FOLDER'], filename))
        return jsonify({'message': 'File uploaded successfully'})
    
    return jsonify({'error': 'Invalid file type'}), 400
