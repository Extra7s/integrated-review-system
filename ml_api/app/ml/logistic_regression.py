import numpy as np

class LogisticRegressionManual:
    def __init__(self, learning_rate=0.01, num_iterations=1000, lambda_param=0.01):
        self.learning_rate = learning_rate
        self.num_iterations = num_iterations
        self.lambda_param = lambda_param # Regularization strength
        self.weights = None
        self.bias = None

    def sigmoid(self, z):
        """
        Sigmoid activation function: 1 / (1 + e^-z)
        """
        # Clip to avoid overflow
        z = np.clip(z, -500, 500)
        return 1 / (1 + np.exp(-z))

    def fit(self, X, y):
        """
        Train the model using Gradient Descent.
        X: Feature matrix (n_samples, n_features)
        y: Labels (n_samples, 1) or (n_samples,)
        """
        n_samples, n_features = X.shape
        self.weights = np.zeros(n_features)
        self.bias = 0

        # Gradient Descent
        for i in range(self.num_iterations):
            linear_model = np.dot(X, self.weights) + self.bias
            y_predicted = self.sigmoid(linear_model)

            # Compute gradients
            # dw = (1/m) * X.T * (y_predicted - y) + (lambda/m) * w
            dw = (1 / n_samples) * np.dot(X.T, (y_predicted - y)) + (self.lambda_param / n_samples) * self.weights
            db = (1 / n_samples) * np.sum(y_predicted - y)

            # Update parameters
            self.weights -= self.learning_rate * dw
            self.bias -= self.learning_rate * db
            
            # Optional: Log cost every 100 iterations
            if i % 100 == 0:
                 # Cost function: -1/m * sum(y*log(h) + (1-y)*log(1-h)) + Reg
                 epsilon = 1e-15
                 y_pred_clipped = np.clip(y_predicted, epsilon, 1 - epsilon)
                 cost = -np.mean(y * np.log(y_pred_clipped) + (1 - y) * np.log(1 - y_pred_clipped))
                 # print(f"Iter {i}: Cost {cost}")

    def predict_proba(self, X):
        """
        Return probability estimates.
        """
        linear_model = np.dot(X, self.weights) + self.bias
        return self.sigmoid(linear_model)

    def predict(self, X, threshold=0.5):
        """
        Predict class labels.
        """
        y_predicted = self.predict_proba(X)
        return [1 if i > threshold else 0 for i in y_predicted]
