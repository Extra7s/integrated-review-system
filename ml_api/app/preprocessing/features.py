import numpy as np
import math

class ManualTFIDF:
    def __init__(self, max_features=1000):
        self.vocab = {} # word -> index
        self.idf = {}   # word -> idf value
        self.max_features = max_features
        self.feature_names = []

    def fit(self, documents):
        """
        documents: list of strings (preprocessed text)
        """
        # Calculate Term Frequency (Global for vocab selection)
        doc_count = len(documents)
        word_counts = {}
        
        for doc in documents:
            unique_words = set(doc.split())
            for word in unique_words:
                word_counts[word] = word_counts.get(word, 0) + 1
        
        # Select top max_features words
        sorted_words = sorted(word_counts.items(), key=lambda x: x[1], reverse=True)
        self.feature_names = [word for word, count in sorted_words[:self.max_features]]
        self.vocab = {word: idx for idx, word in enumerate(self.feature_names)}
        
        # Calculate IDF
        # IDF(t) = log(N / (df(t) + 1))
        for word in self.feature_names:
            df = word_counts[word]
            self.idf[word] = math.log(doc_count / (df + 1)) + 1 # +1 standard smoothing
            
    def transform(self, documents):
        """
        Convert documents to TF-IDF matrix
        """
        rows = len(documents)
        cols = len(self.feature_names)
        matrix = np.zeros((rows, cols))
        
        for i, doc in enumerate(documents):
            words = doc.split()
            word_map = {}
            for w in words:
                word_map[w] = word_map.get(w, 0) + 1
            
            total_words = len(words)
            if total_words == 0:
                continue
                
            for word, freq in word_map.items():
                if word in self.vocab:
                    idx = self.vocab[word]
                    tf = freq / total_words
                    idf_val = self.idf[word]
                    matrix[i, idx] = tf * idf_val
                    
        return matrix

    def fit_transform(self, documents):
        self.fit(documents)
        return self.transform(documents)

def extract_meta_features(text_original):
    """
    Extracts manual features:
    1. Length of review
    2. Exclamation count
    3. Capital letter ratio
    """
    length = len(text_original)
    if length == 0:
        return [0, 0, 0]
        
    exclamations = text_original.count('!')
    capitals = sum(1 for c in text_original if c.isupper())
    capital_ratio = capitals / length
    
    return [length, exclamations, capital_ratio]
