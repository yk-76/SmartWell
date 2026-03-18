🤖 AI Recommendation Service

SmartWell uses a separate AI service to generate health recommendations.
This service must be running before using AI-powered features.

▶️ How to Run the AI Service

Open your terminal

Navigate to the project root directory

cd SmartWell

Run the AI server:

python3 app.py
✅ What This Does

Running this command will:

Start the AI backend service

Enable:

🥗 Food recommendation analysis

🧠 Health insights generation

📊 Smart suggestions in the web system

⚠️ Important Notes

Make sure your .env file is configured correctly

Ensure all required Python dependencies are installed

Keep this service running while using the system

If the service stops, AI features will not work

🧪 (Optional) Install Dependencies First

If you haven’t installed Python packages:

pip install -r requirements.txt
💡 Optional: Create One-Click Start Script
For Linux / Mac (start.sh)
#!/bin/bash
echo "Starting SmartWell AI Service..."
python3 app.py

Run it with:

chmod +x start.sh
./start.sh
For Windows (start.bat)
@echo off
echo Starting SmartWell AI Service...
python app.py
pause
