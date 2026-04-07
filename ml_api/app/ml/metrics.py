import numpy as np

def accuracy_score(y_true, y_pred):
    return np.mean(y_true == y_pred)

def confusion_matrix(y_true, y_pred):
    """
    Returns [[TP, FN], [FP, TN]] for binary classification (Fake=1, Real=0 or vice versa)
    Let's assume:
    1 = Fake (Positive)
    0 = Genuine (Negative)
    
    Structure:
    [[TN, FP],
     [FN, TP]]
    Similar to sklearn's default
    """
    K = len(np.unique(y_true)) 
    # If binary and 0,1 are present, K=2. If only one class present in split, might be 1.
    # We'll stick to binary 0/1 hardcoded for safety in this specific project context
    
    tp = 0
    tn = 0
    fp = 0
    fn = 0
    
    for yt, yp in zip(y_true, y_pred):
        if yt == 1 and yp == 1:
            tp += 1
        elif yt == 0 and yp == 0:
            tn += 1
        elif yt == 0 and yp == 1:
            fp += 1
        elif yt == 1 and yp == 0:
            fn += 1
            
    return np.array([[tn, fp], [fn, tp]])

def precision_score(y_true, y_pred):
    # TP / (TP + FP)
    cm = confusion_matrix(y_true, y_pred)
    tn, fp, fn, tp = cm.ravel()
    
    if (tp + fp) == 0:
        return 0.0
    return tp / (tp + fp)

def recall_score(y_true, y_pred):
    # TP / (TP + FN)
    cm = confusion_matrix(y_true, y_pred)
    tn, fp, fn, tp = cm.ravel()
    
    if (tp + fn) == 0:
        return 0.0
    return tp / (tp + fn)

def f1_score(y_true, y_pred):
    p = precision_score(y_true, y_pred)
    r = recall_score(y_true, y_pred)
    
    if (p + r) == 0:
        return 0.0
    return 2 * (p * r) / (p + r)
