import requests
from bs4 import BeautifulSoup
import re
from app.utils.logger import setup_logger

logger = setup_logger(__name__)

class Scraper:
    def __init__(self):
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }

    def extract_reviews(self, url):
        """
        Extracts reviews from a given URL.
        Returns a list of review texts.
        """
        try:
            logger.info(f"Scraping URL: {url}")
            response = requests.get(url, headers=self.headers, timeout=10)
            
            if response.status_code != 200:
                logger.error(f"Failed to fetch URL. Status code: {response.status_code}")
                return []

            soup = BeautifulSoup(response.content, 'html.parser')
            reviews = []

            # Heuristic 1: Look for common review containers
            # This is a generic approach. For specific sites, we'd need specific selectors.
            potential_reviews = []
            
            # Common classes for reviews
            classes_to_check = [
                'review-text', 'review-content', 'review-body', 
                'comment-text', 'comment-content', 'comment-body',
                'review', 'comment'
            ]

            for cls in classes_to_check:
                elements = soup.find_all(class_=re.compile(cls, re.I))
                for el in elements:
                    text = el.get_text(strip=True)
                    if len(text) > 20: # Filter out very short texts
                        potential_reviews.append(text)
            
            # If no class-based reviews found, try finding paragraphs with substantial text
            if not potential_reviews:
                paragraphs = soup.find_all('p')
                for p in paragraphs:
                    text = p.get_text(strip=True)
                    if len(text) > 50: # Slightly stricter length for raw paragraphs
                        potential_reviews.append(text)

            # Deduplicate
            reviews = list(set(potential_reviews))
            
            logger.info(f"Extracted {len(reviews)} reviews.")
            return reviews

        except Exception as e:
            logger.error(f"Error scraping URL: {e}")
            return []
