import json
import urllib.request

url = 'http://localhost:5000/match'
data = {
    "target_item": {
        "id": 33,
        "title": "Black Wallet",
        "description": "Found a black wallet",
        "image_path": "uploads/item_67b3695279f977.89973873.jpg",
        "type": "found"
    },
    "potential_matches": [
        {
            "id": 32,
            "title": "Black Wallet",
            "description": "Lost a black wallet",
            "image_path": "uploads/item_67b3691f198fe1.51608677.jpg"
        }
    ]
}

req = urllib.request.Request(url, data=json.dumps(data).encode('utf-8'), headers={'Content-Type': 'application/json'})
try:
    response = urllib.request.urlopen(req)
    print(response.read().decode('utf-8'))
except Exception as e:
    print("Error:", e)
