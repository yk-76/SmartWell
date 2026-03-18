# 🏥 SmartWell: AI-Integrated Health Monitor

SmartWell is an **end-to-end health solution** combining **Raspberry Pi hardware**, a **web-based CRUD system**, and **AI-driven diagnostics**.  
It focuses on **early stroke detection** and **nutritional analysis**, providing **real-time health recommendations** based on detected data.

---

## 🚀 Key Functionalities

### 🧠 Stroke Detection
- Detects early stroke symptoms using facial symmetry analysis
- AI-powered model for real-time alerts

### 🥗 Food Detection
- Identifies food using Pi Camera
- Provides nutritional insights and healthier alternatives

### ⚖️ BMI Calculator
- Tracks BMI with personalized health tips

### 📂 Health Journal (CRUD System)
- Save records, view history, and track health progress

---

## 🛠️ Hardware Requirements
- Raspberry Pi (4 or 5 recommended)
- Raspberry Pi Camera Module
- Stable Internet Connection

---

## 📦 Setup & Installation

### 1️⃣ Clone Project
```bash
git clone https://github.com/your-username/SmartWell.git
```

### 2️⃣ Go to Project Folder
```bash
cd SmartWell
```

### 3️⃣ Setup Environment File
```bash
mv .env.example .env
```

> Fill in your `.env` file:
> - Database credentials
> - GitHub Token
> - Hugging Face Token

### 4️⃣ Install Dependencies
```bash
pip install -r requirements.txt
```

### 5️⃣ Run AI Recommendation Service ⚠️ (REQUIRED)
```bash
python3 app.py
```

> ✅ Must be running for:
> - Food recommendations
> - Health insights
> - AI analysis

### 6️⃣ Run Raspberry Pi Detection (Optional)
```bash
cd RaspberryPi
```
```bash
python3 main_detection.py
```

### 7️⃣ Database Setup

Open `db_script/database.sql`, copy all contents, then run it in **phpMyAdmin** or **MySQL Workbench**.

### 8️⃣ Web Setup

- Point your PHP server to the project root
- Configure `config.php` to use `.env`

---

## ⚙️ Configuration
```env
AI_MODEL_NAME=DeepSeek
```

---

## 📁 Project Structure
```
SmartWell/
│── db_script/
│── RaspberryPi/
│── food_detection_model/
│── stroke_models/
│── app.py
│── config.php
│── .env.example
```

---

## ⚠️ Important Notes

- Do **NOT** upload `.env` to GitHub
- Ensure internet connection for AI APIs
- Keep `python3 app.py` running at all times

---

## 🚀 Quick Start (Fastest Way)

**Step 1** — Navigate to project folder:
```bash
cd SmartWell
```

**Step 2** — Install dependencies:
```bash
pip install -r requirements.txt
```

**Step 3** — Start the AI service:
```bash
python3 app.py
```

---

## 💡 Optional: One-Click Start Script

### 🐧 Linux / Mac

Create the script file:
```bash
touch start.sh
```

Open and add the following content:
```bash
#!/bin/bash
python3 app.py
```

Make it executable:
```bash
chmod +x start.sh
```

Run it:
```bash
./start.sh
```

### 🪟 Windows

Create a file named `start.bat` and add the following content:
```bat
@echo off
python app.py
pause
```

---

## 🧑‍💻 Authors

- Your Name
- Your Team

---

## ⭐ Contributing

Pull requests are welcome!

---

## 📜 License

For educational and research purposes only.
