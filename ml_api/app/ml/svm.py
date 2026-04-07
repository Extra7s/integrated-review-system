import numpy as np

class LinearSVMManual:
    def __init__(self, learning_rate=0.001, lambda_param=0.01, num_iterations=1000):
        self.lr = learning_rate
        self.lambda_param = lambda_param
        self.num_iterations = num_iterations
        self.weights = None
        self.bias = None

    def fit(self, X, y):
        """
        Train using Sub-gradient Descent with Hinge Loss.
        Map y from {0, 1} to {-1, 1} for SVM math.
        """
        n_samples, n_features = X.shape
        
        # Convert 0/1 to -1/1
        y_ = np.where(y <= 0, -1, 1)
        
        self.weights = np.zeros(n_features)
        self.bias = 0

        for idx in range(self.num_iterations):
            for i, x_i in enumerate(X):
                # Check condition y_i * (w.x_i - b) >= 1
                condition = y_[i] * (np.dot(x_i, self.weights) - self.bias) >= 1
                
                if condition:
                    # Logic: Only regularizer gradient
                    dw = 2 * self.lambda_param * self.weights
                    db = 0
                else:
                    # Logic: Regularizer - y_i * x_i
                    dw = 2 * self.lambda_param * self.weights - np.dot(x_i, y_[i])
                    db = y_[i] # Gradient for bias in hinge loss is -y_i, but update rule accumulates it differently, here we typically do w = w - lr*dw, b = b - lr*db.
                    # Correct update for bias in hinge loss context:
                    # Loss = max(0, 1 - y(wx - b)).
                    # If 1 - y(wx-b) > 0: dL/db = -y * (-1) = y.
                    # Update: b = b - lr * y
                    
                self.weights -= self.lr * dw
                self.bias -= self.lr * (-db) # Adjusting sign for descent direction

    def sigmoid(self, z):
        return 1 / (1 + np.exp(-z))

    def predict_proba(self, X):
        """
        Return probability estimates using sigmoid of decision function.
        """
        decision = np.dot(X, self.weights) - self.bias
        return self.sigmoid(decision)

    def predict(self, X):
        """
        Predict class labels.
        """
        approx = np.dot(X, self.weights) - self.bias
        # Sign returns -1 or 1, map back to 0 or 1
        return np.where(np.sign(approx) == -1, 0, 1)
