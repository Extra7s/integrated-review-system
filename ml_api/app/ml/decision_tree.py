import numpy as np

class Node:
    def __init__(self, feature_index=None, threshold=None, left=None, right=None, value=None):
        self.feature_index = feature_index
        self.threshold = threshold
        self.left = left
        self.right = right
        self.value = value

class DecisionTreeManual:
    def __init__(self, min_samples_split=2, max_depth=100):
        self.min_samples_split = min_samples_split
        self.max_depth = max_depth
        self.root = None

    def fit(self, X, y):
        """
        Build the tree.
        """
        self.root = self._build_tree(X, y)

    def _build_tree(self, X, y, depth=0):
        num_samples, num_features = X.shape
        num_labels = len(np.unique(y))

        # Stopping criteria
        if (depth >= self.max_depth or num_labels == 1 or num_samples < self.min_samples_split):
            leaf_value = self._calculate_leaf_value(y)
            return Node(value=leaf_value)

        # Find best split
        best_split = self._get_best_split(X, y, num_samples, num_features)
        
        if best_split["gain"] > 0:
            left_subtree = self._build_tree(best_split["X_left"], best_split["y_left"], depth + 1)
            right_subtree = self._build_tree(best_split["X_right"], best_split["y_right"], depth + 1)
            return Node(best_split["feature_index"], best_split["threshold"], left_subtree, right_subtree)
        
        return Node(value=self._calculate_leaf_value(y))

    def _get_best_split(self, X, y, num_samples, num_features):
        best_split = {"gain": -1}
        max_info_gain = -float("inf")

        for feature_index in range(num_features):
            feature_values = X[:, feature_index]
            possible_thresholds = np.unique(feature_values)
            
            # Optimization: don't check every single threshold if continuous
            if len(possible_thresholds) > 10:
                possible_thresholds = np.percentile(possible_thresholds, [25, 50, 75])

            for threshold in possible_thresholds:
                dataset_left, dataset_right = self._split(X[:, feature_index], threshold)
                
                if len(dataset_left) > 0 and len(dataset_right) > 0:
                    y_left, y_right = y[dataset_left], y[dataset_right]
                    curr_info_gain = self._information_gain(y, y_left, y_right)
                    
                    if curr_info_gain > max_info_gain:
                        best_split["feature_index"] = feature_index
                        best_split["threshold"] = threshold
                        best_split["X_left"] = X[dataset_left]
                        best_split["y_left"] = y_left
                        best_split["X_right"] = X[dataset_right]
                        best_split["y_right"] = y_right
                        best_split["gain"] = curr_info_gain
                        max_info_gain = curr_info_gain
                        
        return best_split

    def _split(self, feature_column, threshold):
        left_idxs = np.argwhere(feature_column <= threshold).flatten()
        right_idxs = np.argwhere(feature_column > threshold).flatten()
        return left_idxs, right_idxs

    def _information_gain(self, parent, l_child, r_child):
        weight_l = len(l_child) / len(parent)
        weight_r = len(r_child) / len(parent)
        gain = self._gini_index(parent) - (weight_l * self._gini_index(l_child) + weight_r * self._gini_index(r_child))
        return gain

    def _gini_index(self, y):
        """
        Gini = 1 - sum(p_i^2)
        """
        class_labels = np.unique(y)
        gini = 0
        for cls in class_labels:
            p_cls = len(y[y == cls]) / len(y)
            gini += p_cls ** 2
        return 1 - gini

    def _calculate_leaf_value(self, y):
        # Return probability of class 1
        if len(y) == 0: return 0.0
        return np.mean(y)

    def predict_proba(self, X):
        return [self._make_prediction(x, self.root) for x in X]

    def predict(self, X, threshold=0.5):
        probs = self.predict_proba(X)
        return [1 if p > threshold else 0 for p in probs]

    def _make_prediction(self, x, tree):
        if tree.value is not None:
            return tree.value
        feature_val = x[tree.feature_index]
        if feature_val <= tree.threshold:
            return self._make_prediction(x, tree.left)
        else:
            return self._make_prediction(x, tree.right)
