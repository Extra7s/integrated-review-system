from flask import Flask
from config import Config

import os

def create_app(config_class=Config):
    # Set template/static paths relative to project root (one level up from this file)
    base_dir = os.path.abspath(os.path.dirname(os.path.dirname(__file__)))
    template_dir = os.path.join(base_dir, 'templates')
    static_dir = os.path.join(base_dir, 'static')

    app = Flask(__name__, template_folder=template_dir, static_folder=static_dir)
    app.config.from_object(config_class)

    from app.routes import main
    app.register_blueprint(main)

    return app
