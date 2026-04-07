import re

# Custom list of stopwords to avoid NLTK dependency for "manual" requirement
STOPWORDS = {
    'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', "you're", "you've", "you'll", "you'd", 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', "she's", 'her', 'hers', 'herself', 'it', "it's", 'its', 'itself', 'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', "that'll", 'these', 'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until', 'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', "don't", 'should', "should've", 'now', 'd', 'll', 'm', 'o', 're', 've', 'y', 'ain', 'aren', "aren't", 'couldn', "couldn't", 'didn', "didn't", 'doesn', "doesn't", 'hadn', "hadn't", 'hasn', "hasn't", 'haven', "haven't", 'isn', "isn't", 'ma', 'mightn', "mightn't", 'mustn', "mustn't", 'needn', "needn't", 'shan', "shan't", 'shouldn', "shouldn't", 'wasn', "wasn't", 'weren', "weren't", 'won', "won't", 'wouldn', "wouldn't"
}

def clean_text(text):
    """
    1. Lowercase
    2. Remove punctuation/special characters
    3. Remove multiple spaces
    """
    if not isinstance(text, str):
        return ""
    
    text = text.lower()
    # Remove punctuation (keep only alphanumeric and spaces)
    text = re.sub(r'[^a-z0-9\s]', '', text)
    # Collapse multiple spaces
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def tokenize(text):
    """Simple whitespace tokenization"""
    return text.split()

def remove_stopwords(tokens):
    """Remove tokens present in STOPWORDS set"""
    return [t for t in tokens if t not in STOPWORDS]

def stem(token):
    """
    Manual simple stemming implementation (Porter-like suffix stripping).
    This is a simplified version for demonstration purposes.
    """
    if len(token) < 4:
        return token
    
    # Step 1a
    if token.endswith('sses'):
        token = token[:-2]
    elif token.endswith('ies'):
        token = token[:-2]
    elif token.endswith('ss'):
        pass # do nothing
    elif token.endswith('s'):
        token = token[:-1]
        
    # Step 1b
    if token.endswith('eed'):
        if len(token) > 4: # measure check simplified
            token = token[:-1]
    elif token.endswith('ing'):
        token = token[:-3]
    elif token.endswith('ed'):
        token = token[:-2]
        
    return token

def preprocess_pipeline(text):
    """
    Full pipeline: Clean -> Tokenize -> Remove Stopwords -> Stem
    Returns: List of processed tokens
    """
    text = clean_text(text)
    tokens = tokenize(text)
    tokens = remove_stopwords(tokens)
    tokens = [stem(t) for t in tokens]
    return " ".join(tokens) # Return string for TF-IDF
