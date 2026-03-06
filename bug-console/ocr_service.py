import os
import cv2
import numpy as np
import pytesseract
from flask import Flask, request, jsonify
from flask_cors import CORS # <-- Import CORS
from PIL import Image
import io
import json

# ... (optional Tesseract path) ...

UPLOAD_FOLDER = 'uploads_py'
if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

app = Flask(__name__)
CORS(app) # <-- Initialize CORS for all routes
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024

# --- Helper Function: Order points ---
def order_points(pts):
    # ... (function remains the same) ...
    xSorted = pts[np.argsort(pts[:, 0]), :]
    leftMost = xSorted[:2, :]
    rightMost = xSorted[2:, :]
    leftMost = leftMost[np.argsort(leftMost[:, 1]), :]
    (tl, bl) = leftMost
    rightMost = rightMost[np.argsort(rightMost[:, 1]), :]
    (tr, br) = rightMost
    return np.array([tl, tr, br, bl], dtype="float32")

# --- Flask Route ---
@app.route('/process-schedule', methods=['POST'])
def process_schedule():
    # --- 1. Get Input Data ---
    # ... (input handling remains the same) ...
    if 'image' not in request.files: return jsonify({"error": "No image file provided"}), 400
    file = request.files['image']
    if file.filename == '': return jsonify({"error": "No image file selected"}), 400
    coords_json = request.form.get('coords')
    if not coords_json: return jsonify({"error": "No coordinates provided"}), 400
    try:
        coords = json.loads(coords_json)
        if len(coords) != 4: raise ValueError("Exactly 4 coordinates required.")
        src_pts = np.array(coords, dtype="float32")
    except Exception as e: return jsonify({"error": f"Invalid coordinates format: {e}"}), 400

    # --- 2. Load Image ---
    # ... (image loading remains the same) ...
    try:
        img_stream = file.read()
        pil_image = Image.open(io.BytesIO(img_stream)).convert('RGB')
        image = np.array(pil_image)
        image = image[:, :, ::-1].copy() # RGB to BGR
    except Exception as e: return jsonify({"error": f"Failed to load image: {e}"}), 400

    # --- 3. Perspective Transformation ---
    # ... (transformation logic remains the same) ...
    try:
        ordered_src_pts = order_points(src_pts)
        (tl, tr, br, bl) = ordered_src_pts
        widthA = np.sqrt(((br[0] - bl[0]) ** 2) + ((br[1] - bl[1]) ** 2))
        widthB = np.sqrt(((tr[0] - tl[0]) ** 2) + ((tr[1] - tl[1]) ** 2))
        maxWidth = max(int(widthA), int(widthB))
        heightA = np.sqrt(((tr[0] - br[0]) ** 2) + ((tr[1] - br[1]) ** 2))
        heightB = np.sqrt(((tl[0] - bl[0]) ** 2) + ((tl[1] - bl[1]) ** 2))
        maxHeight = max(int(heightA), int(heightB))
        dst_pts = np.array([[0, 0], [maxWidth - 1, 0], [maxWidth - 1, maxHeight - 1], [0, maxHeight - 1]], dtype="float32")
        M = cv2.getPerspectiveTransform(ordered_src_pts, dst_pts)
        warped_image = cv2.warpPerspective(image, M, (maxWidth, maxHeight))
    except Exception as e: return jsonify({"error": f"Perspective transformation failed: {e}"}), 500

    # --- 4. OCR on Warped Image ---
    # ... (OCR logic remains the same) ...
    try:
        gray_image = cv2.cvtColor(warped_image, cv2.COLOR_BGR2GRAY)
        ocr_config = r'-l eng --psm 3'
        ocr_text = pytesseract.image_to_string(gray_image, config=ocr_config)
    except Exception as e:
        if isinstance(e, pytesseract.TesseractNotFoundError): return jsonify({"error": "Tesseract command not found."}), 500
        else: return jsonify({"error": f"OCR failed: {e}"}), 500

    # --- 5. Return Result ---
    return jsonify({"ocr_text": ocr_text})

# --- Run Flask App ---
if __name__ == '__main__':
    # Port changed to 5002 based on your screenshot
    app.run(host='0.0.0.0', port=5002, debug=True)