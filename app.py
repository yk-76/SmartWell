import os
from flask import Flask, request, jsonify
from flask_cors import CORS
from azure.ai.inference import ChatCompletionsClient
from azure.ai.inference.models import SystemMessage, UserMessage
from azure.core.credentials import AzureKeyCredential

app = Flask(__name__)
CORS(app)

GITHUB_TOKEN = os.environ.get("GITHUB_TOKEN")

endpoint = os.getenv("AI_ENDPOINT", "https://models.github.ai/inference")
os.getenv("AI_MODEL_NAME")

client = ChatCompletionsClient(
    endpoint=endpoint,
    credential=AzureKeyCredential(GITHUB_TOKEN),
)

@app.route('/get_ai_advice', methods=['POST'])
def get_ai_advice():
    try:
        data = request.json
        food_label = data.get("label", "")

        prompt = f"""
        A user scanned a food labeled '{food_label}'.
        
        1. Is this healthy or unhealthy? Explain.
        
        2. Mention stroke risk (if any).
        
        3. Suggest healthier alternatives.

        4. Make all the answer short.
        """

        response = client.complete(
            messages=[
                SystemMessage("You are a helpful food and health assistant."),
                UserMessage(prompt),
            ],
            temperature=0.8,
            top_p=0.1,
            max_tokens=2048,
            model=model
        )

        result = response.choices[0].message.content
        return jsonify({"advice": result})

    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)
