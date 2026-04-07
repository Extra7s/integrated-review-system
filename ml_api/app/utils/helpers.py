import pandas as pd
import pickle
import os
from app.utils.logger import setup_logger

logger = setup_logger(__name__)

def load_dataset(filepath):
    """
    Load CSV dataset using pandas.
    Expected columns: 'text', 'label' (or similar)
    """
    try:
        df = pd.read_csv(filepath)
        
        # Handle "fake reviews dataset.csv" schema
        if 'text_' in df.columns:
            df.rename(columns={'text_': 'text'}, inplace=True)
            
        if 'label' in df.columns:
            # Map labels if they are OR/CG
            # OR = Original Review (Genuine) -> 1
            # CG = Computer Generated (Fake) -> 0
            # Wait, user asked for "Fake" detection. Usually 1 is the positive class (target).
            # If detecting FAKE, then Fake should be 1. 
            # In my plan I said: 'CG' -> 0 (Fake), 'OR' -> 1 (Genuine). 
            # But earlier in routes.py checks: result = "Fake" if prediction == 1.
            # So Fake matches 1.
            # Thus: CG -> 1, OR -> 0.
            
            # Let's check unique values first to be safe, but for now we implement mapping
            df['label'] = df['label'].map({'CG': 1, 'OR': 0})
            # Drop rows where mapping failed (if any other labels exist)
            df.dropna(subset=['label'], inplace=True)

        # Basic validation
        if 'text' not in df.columns or 'label' not in df.columns:
             # Try to guess or return error
             # For simplicity, we assume strict format or rename first 2 cols
             # But first let's try standardizing
             pass
            
        # Drop missing
        df.dropna(subset=['text', 'label'], inplace=True)
        return df
    except Exception as e:
        logger.error(f"Error loading dataset: {e}")
        return None

def save_model(model, filename, folder='app/models/'):
    """
    Save model to pickle
    """
    try:
        if not os.path.exists(folder):
            os.makedirs(folder)
        path = os.path.join(folder, filename)
        with open(path, 'wb') as f:
            pickle.dump(model, f)
        logger.info(f"Model saved to {path}")
        return True
    except Exception as e:
        logger.error(f"Error saving model: {e}")
        return False

def load_model(filename, folder='app/models/'):
    """
    Load model from pickle
    """
    try:
        path = os.path.join(folder, filename)
        if not os.path.exists(path):
            return None
        with open(path, 'rb') as f:
            model = pickle.load(f)
        return model
    except Exception as e:
        logger.error(f"Error loading model {filename}: {e}")
        return None
