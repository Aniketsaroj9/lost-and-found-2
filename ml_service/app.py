import os
import cv2
import numpy as np
from flask import Flask, request, jsonify
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

app = Flask(__name__)

# Base directory mapping where images are stored by PHP
BASE_IMG_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))

def calculate_text_similarity(text1, text2):
    """
    Calculate text similarity using TF-IDF and Cosine Similarity.
    """
    if not text1 or not text2:
        return 0.0
    
    vectorizer = TfidfVectorizer(stop_words='english')
    try:
        tfidf_matrix = vectorizer.fit_transform([text1, text2])
        sim_score = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])[0][0]
        return float(sim_score) * 100.0 # Return as percentage
    except Exception as e:
        print(f"Text similarity error: {e}")
        return 0.0

def calculate_image_similarity(img_path1, img_path2):
    """
    Calculate image similarity using OpenCV ORB feature matching.
    """
    if not img_path1 or not img_path2:
        return 0.0
    
    full_path1 = os.path.join(BASE_IMG_DIR, img_path1)
    full_path2 = os.path.join(BASE_IMG_DIR, img_path2)

    if not os.path.exists(full_path1) or not os.path.exists(full_path2):
        print(f"Image not found. PATH1: {full_path1}, PATH2: {full_path2}")
        return 0.0

    try:
        # Load images in grayscale
        img1 = cv2.imread(full_path1, cv2.IMREAD_GRAYSCALE)
        img2 = cv2.imread(full_path2, cv2.IMREAD_GRAYSCALE)

        if img1 is None or img2 is None:
             return 0.0

        # Resize for faster and more consistent processing
        img1 = cv2.resize(img1, (500, 500))
        img2 = cv2.resize(img2, (500, 500))

        # Initiate ORB detector
        orb = cv2.ORB_create()

        # Find the keypoints and descriptors with ORB
        kp1, des1 = orb.detectAndCompute(img1, None)
        kp2, des2 = orb.detectAndCompute(img2, None)

        if des1 is None or des2 is None:
            return 0.0

        # Create BFMatcher object
        bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)

        # Match descriptors.
        matches = bf.match(des1, des2)

        # Sort them in the order of their distance.
        matches = sorted(matches, key = lambda x:x.distance)

        # Calculate a score based on the number of good matches and total keypoints
        # The lower the distance, the better. We'll use a threshold distance to define "good"
        good_matches = [m for m in matches if m.distance < 50]
        
        # Normalize score
        max_kp = max(len(kp1), len(kp2))
        if max_kp == 0:
             return 0.0

        # A simple empirical formula mapping matches to a percentage score
        score = (len(good_matches) / max_kp) * 100 * 5 # multiplier to push scores up 
        
        return min(float(score), 100.0)

    except Exception as e:
        print(f"Image similarity error: {e}")
        return 0.0


@app.route('/match', methods=['POST'])
def match_items():
    """
    Expects JSON payload with:
    {
        "target_item": {
            "id": 1,
            "title": "Black Wallet",
            "description": "Found near library, leather",
            "image_path": "uploads/item_1.jpg",
            "type": "found"
        },
        "potential_matches": [
            {
                "id": 2,
                "title": "Wallet lost",
                "description": "Black leather wallet",
                "image_path": "uploads/item_2.jpg"
            },
            ...
        ]
    }
    """
    data = request.json
    
    if not data or 'target_item' not in data or 'potential_matches' not in data:
        return jsonify({"error": "Invalid request payload"}), 400

    target = data['target_item']
    candidates = data['potential_matches']

    target_text = f"{target.get('title', '')} {target.get('description', '')}"
    target_img = target.get('image_path', '')

    results = []

    for candidate in candidates:
        cand_text = f"{candidate.get('title', '')} {candidate.get('description', '')}"
        cand_img = candidate.get('image_path', '')

        text_score = calculate_text_similarity(target_text, cand_text)
        image_score = calculate_image_similarity(target_img, cand_img)

        # Final Score Formula (50% Text, 50% Image)
        # If no image exists, base purely on text (100% Text)
        if cand_img and target_img:
            final_score = (text_score * 0.5) + (image_score * 0.5)
        else:
            final_score = text_score

        # Check against threshold
        if final_score >= 70.0:
            results.append({
                "target_id": target.get('id'),
                "candidate_id": candidate.get('id'),
                "text_score": round(text_score, 2),
                "image_score": round(image_score, 2),
                "final_score": round(final_score, 2)
            })

    # Sort results by highest score
    results.sort(key=lambda x: x['final_score'], reverse=True)

    return jsonify({"status": "success", "matches": results}), 200

if __name__ == '__main__':
    # Run slightly differently in prod, this is local dev server
    app.run(host='0.0.0.0', port=5000, debug=True)
