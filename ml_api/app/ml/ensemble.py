from collections import Counter
import numpy as np

class EnsembleVoteClassifier:
    def __init__(self, classifiers):
        """
        classifiers: list of instantiated and TRAINED classifier objects
        """
        self.classifiers = classifiers

    def fit(self, X, y):
        # Assumes individual classifiers are already fit or fit them here
        # For this system, we will assume they are fit externally or we can add logic here
        for clf in self.classifiers:
            clf.fit(X, y)

    def predict_proba(self, X):
        """
        Average probabilities from all classifiers.
        """
        probas = np.array([clf.predict_proba(X) for clf in self.classifiers])
        # probas shape: (n_classifiers, n_samples)
        # Average across classifiers (axis 0)
        avg_proba = np.mean(probas, axis=0)
        return avg_proba

    def predict(self, X):
        # Collect predictions from all classifiers
        predictions = np.array([clf.predict(X) for clf in self.classifiers])
        
        # Majority vote
        ensemble_predictions = []
        # Transpose to get predictions per sample
        for sample_predictions in predictions.T:
            # sample_predictions is [pred_model1, pred_model2, pred_model3]
            c = Counter(sample_predictions)
            vote = c.most_common(1)[0][0]
            ensemble_predictions.append(vote)
            
        return np.array(ensemble_predictions)
