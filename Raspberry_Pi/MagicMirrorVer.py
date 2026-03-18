import tkinter as tk
from tkinter import messagebox
import mysql.connector
import subprocess
import os
import sys
import threading
import time
import json

# --- Database Config ---
with open('db_config.json', 'r') as f:
    db_config = json.load(f)

try:
    conn = mysql.connector.connect(**db_config)
    print("Connected to DB!")
    conn.close()
except Exception as e:
    print("DB Connection failed:", e)

current_user_id = None
current_username = None

try:
    import bcrypt
    BCRYPT_AVAILABLE = True
except ImportError:
    BCRYPT_AVAILABLE = False
    print("Warning: bcrypt not available. Only plain text passwords will be supported.")

# --- Helper: Dynamic Fullscreen Sizing ---
def get_fullscreen_font(size_factor):
    # scale the font size with screen height
    screen_height = root.winfo_screenheight()
    return ("Segoe UI", int(screen_height * size_factor))

def center_pack(widget, pady=0):
    widget.pack(expand=True, fill="both", pady=pady)

# --- App Color Theme: BLACK AND WHITE ---
BG = "#000000"
FG = "#FFFFFF"
BUTTON_BG = "#000000"
BUTTON_FG = "#FFFFFF"
BUTTON_BORDER = "#FFFFFF"
ENTRY_BG = "#222222"
ENTRY_FG = "#FFFFFF"
HIGHLIGHT = "#FFFFFF"

# --- Tkinter UI (Fullscreen, Black & White) ---
root = tk.Tk()
root.title("Stroke Detection System")
root.configure(bg=BG)
root.attributes("-fullscreen", True)
root.update()

# === LOGIN FRAME ===
login_frame = tk.Frame(root, bg=BG)
center_pack(login_frame)

# --- TOP SPACER ---
top_spacer = tk.Frame(login_frame, bg=BG)
top_spacer.pack(side="top", expand=True, fill="both")

# --- MAIN CONTAINER ---
login_container = tk.Frame(login_frame, bg=BG)
login_container.pack(side="top", pady=0)


# Header
header_label = tk.Label(
    login_container,
    text="Stroke Detection System",
    font=get_fullscreen_font(0.03),
    bg=BG,
    fg=FG
)
center_pack(header_label, pady=(0, 5))

login_label = tk.Label(
    login_container,
    text="Login Page",
    font=get_fullscreen_font(0.02),
    bg=BG,
    fg=FG
)
center_pack(login_label, pady=(0, 30))

# Small spacer after "Login Page" heading
login_label_spacer = tk.Frame(login_container, height=100, bg=BG)
login_label_spacer.pack(fill="x")


# Username entry
username_label = tk.Label(
    login_container, text="Username", font=get_fullscreen_font(0.02), bg=BG, fg=FG
)
center_pack(username_label)
username_entry = tk.Entry(
    login_container,
    font=get_fullscreen_font(0.02),
    bg=ENTRY_BG,
    fg=ENTRY_FG,
    insertbackground=FG,
    relief="solid",
    bd=2,
    highlightthickness=2,
    highlightbackground=HIGHLIGHT,
    justify="center"
)
username_entry.pack(ipady=15, pady=(0, 25), ipadx=5, fill="x", padx=120)

# Password entry
password_label = tk.Label(
    login_container, text="Password", font=get_fullscreen_font(0.02), bg=BG, fg=FG
)
center_pack(password_label)
password_entry = tk.Entry(
    login_container,
    show="*",
    font=get_fullscreen_font(0.02),
    bg=ENTRY_BG,
    fg=ENTRY_FG,
    insertbackground=FG,
    relief="solid",
    bd=2,
    highlightthickness=2,
    highlightbackground=HIGHLIGHT,
    justify="center"
)
password_entry.pack(ipady=15, pady=(0, 35), ipadx=5, fill="x", padx=120)

# Login and QR code buttons
def style_button(btn):
    btn.configure(
        bg=BUTTON_BG, fg=BUTTON_FG,
        font=get_fullscreen_font(0.02),
        relief="flat",
        bd=2,
        highlightthickness=2,
        highlightbackground=BUTTON_BORDER,
        activebackground=BUTTON_BG,
        activeforeground=BUTTON_FG,
        cursor="hand2"
    )

# Small spacer before Login button
login_btn_spacer = tk.Frame(login_container, height=100, bg=BG)
login_btn_spacer.pack(fill="x")

login_button = tk.Button(login_container, text="Log In", command=lambda: validate_login())
style_button(login_button)
login_button.pack(fill="x", pady=(0, 20), padx=120, ipady=10)

qr_login_button = tk.Button(login_container, text="Scan QR Code to Login", command=lambda: scan_qr_and_login())
style_button(qr_login_button)
qr_login_button.pack(fill="x", padx=120, ipady=10)

# --- BOTTOM SPACER ---
bottom_spacer = tk.Frame(login_frame, bg=BG)
bottom_spacer.pack(side="top", expand=True, fill="both")



# === MAIN MENU FRAME ===
main_menu_frame = tk.Frame(root, bg=BG)

# --- TOP SPACER ---
top_spacer = tk.Frame(main_menu_frame, bg=BG)
top_spacer.pack(side="top", expand=True, fill="both")

menu_container = tk.Frame(main_menu_frame, bg=BG)
menu_container.pack(side="top")

menu_header = tk.Label(
    menu_container,
    text="Main Menu",
    font=get_fullscreen_font(0.04),
    bg=BG,
    fg=FG
)
center_pack(menu_header, pady=(0, 5))  # reduce padding below header

welcome_label = tk.Label(
    menu_container,
    text="",
    font=get_fullscreen_font(0.02),
    bg=BG,
    fg=FG
)
center_pack(welcome_label, pady=(0, 0))  # No extra bottom space

# Small spacer (optional, 6 pixels)
header_spacer = tk.Frame(menu_container, height=100, bg=BG)
header_spacer.pack(fill="x")

# Menu buttons
menu_buttons_frame = tk.Frame(menu_container, bg=BG)
center_pack(menu_buttons_frame)

stroke_menu_button = tk.Button(
    menu_buttons_frame, text="🔍 Stroke Detection", command=lambda: launch_stroke_detection()
)
style_button(stroke_menu_button)
stroke_menu_button.pack(fill="x", pady=(0, 40), padx=0, ipady=20)

logout_button = tk.Button(
    menu_buttons_frame, text="⏻ Logout", command=lambda: logout()
)
style_button(logout_button)
logout_button.pack(fill="x", padx=0, ipady=20)

# --- BOTTOM SPACER ---
bottom_spacer = tk.Frame(main_menu_frame, bg=BG)
bottom_spacer.pack(side="top", expand=True, fill="both")



# === ANALYSIS RESULT FRAME ===
result_frame = tk.Frame(root, bg=BG)

top_spacer = tk.Frame(result_frame, bg=BG)
top_spacer.pack(side="top", expand=True, fill="both")

result_label = tk.Label(
    result_frame,
    text="",
    font=get_fullscreen_font(0.02),
    bg=BG,
    fg=FG
)
center_pack(result_label, pady=(0, 0))

result_text = tk.Label(
    result_frame,
    text="",
    font=get_fullscreen_font(0.02),
    bg=BG,
    fg=FG,
    wraplength=root.winfo_screenwidth() - 100,
    justify="center"
)
center_pack(result_text, pady=(0, 0))

return_menu_button = tk.Button(result_frame, text="Return to Main Menu", command=lambda: return_to_menu())
style_button(return_menu_button)
return_menu_button.pack(pady=30, ipadx=20, ipady=10)

bottom_spacer = tk.Frame(result_frame, bg=BG)
bottom_spacer.pack(side="top", expand=True, fill="both")

# --- Logic ---
def validate_login():
    global current_user_id, current_username
    username = username_entry.get().strip()
    password = password_entry.get().strip()
    if not username or not password:
        messagebox.showerror("Input Error", "Please enter both username and password.")
        return

    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        cursor.execute("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_general_ci'")
        query = "SELECT UserID, UserName, Password FROM user WHERE UserName = %s"
        cursor.execute(query, (username,))
        result = cursor.fetchone()
        cursor.close()
        conn.close()

        if result:
            user_id, stored_username, stored_password = result
            if stored_password.startswith(('$2y$', '$2a$', '$2b$')):
                if not BCRYPT_AVAILABLE:
                    messagebox.showerror("Login Error", "bcrypt not installed.")
                    return
                if bcrypt.checkpw(password.encode('utf-8'), stored_password.encode('utf-8')):
                    current_user_id = user_id
                    current_username = username
                    show_main_menu()
                    return
                else:
                    messagebox.showerror("Login Failed", "Invalid username or password.")
                    return
            else:
                if stored_password == password:
                    current_user_id = user_id
                    current_username = username
                    show_main_menu()
                    return
                else:
                    messagebox.showerror("Login Failed", "Invalid username or password.")
                    return
        else:
            messagebox.showerror("Login Failed", "Invalid username or password.")

    except mysql.connector.Error as err:
        messagebox.showerror("Database Error", f"Error connecting to database:\n{err}")
    except Exception as e:
        messagebox.showerror("Error", f"An unexpected error occurred:\n{e}")

def validate_qr_login(qr_username, qr_token):
    global current_user_id, current_username
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        cursor.execute("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_general_ci'")
        query = "SELECT UserID, UserName FROM user WHERE UserName = %s AND qr_token = %s"
        cursor.execute(query, (qr_username, qr_token))
        result = cursor.fetchone()
        cursor.close()
        conn.close()

        if result:
            user_id, user_name = result
            current_user_id = user_id
            current_username = user_name
            return True
        else:
            return False
    except mysql.connector.Error as err:
        messagebox.showerror("Database Error", f"Error connecting to database:\n{err}")
        return False
    except Exception as e:
        messagebox.showerror("Error", f"An unexpected error occurred:\n{e}")
        return False

def show_main_menu():
    global current_user_id, current_username
    os.environ['CURRENT_USER_ID'] = str(current_user_id)
    os.environ['CURRENT_USERNAME'] = current_username
    try:
        with open('current_user.txt', 'w') as f:
            f.write(f"{current_user_id}\n{current_username}")
    except Exception as e:
        print(f"Warning: Could not save user info to file: {e}")

    login_frame.pack_forget()
    result_frame.pack_forget()
    main_menu_frame.pack(fill="both", expand=True)
    welcome_label.config(text=f"Welcome, {current_username}!")

def logout():
    global current_user_id, current_username
    current_user_id = None
    current_username = None
    if 'CURRENT_USER_ID' in os.environ:
        del os.environ['CURRENT_USER_ID']
    if 'CURRENT_USERNAME' in os.environ:
        del os.environ['CURRENT_USERNAME']
    try:
        if os.path.exists('current_user.txt'):
            os.remove('current_user.txt')
    except Exception as e:
        print(f"Warning: Could not remove user info file: {e}")

    username_entry.delete(0, tk.END)
    password_entry.delete(0, tk.END)
    main_menu_frame.pack_forget()
    result_frame.pack_forget()
    login_frame.pack(fill="both", expand=True)
    username_entry.focus()

def launch_menu_app():
    messagebox.showinfo("Not Implemented", "Menu options not implemented in this sample.")

def on_enter_key(event):
    validate_login()

def scan_qr_and_login():
    try:
        from picamera2 import Picamera2
        import cv2
        from pyzbar import pyzbar
        import tkinter as tk
        import json

        qr_username = None
        qr_token = None

        win_name = "QR Code Scanner (press q to quit)"
        window_width = 960
        window_height = 720

        picam2 = Picamera2()
        picam2.start()
        found = False

        cv2.namedWindow(win_name, cv2.WINDOW_NORMAL)
        cv2.resizeWindow(win_name, window_width, window_height)

        root_tk = tk.Tk()
        screen_w = root_tk.winfo_screenwidth()
        screen_h = root_tk.winfo_screenheight()
        root_tk.destroy()

        x = (screen_w - window_width) // 2
        y = (screen_h - window_height) // 2
        
        import numpy as np
        dummy = np.zeros((window_height, window_width, 3), dtype=np.uint8)
        cv2.imshow(win_name, dummy)
        cv2.waitKey(1)  # let OS render the window
        
        cv2.moveWindow(win_name, x, y)

        while True:
            frame = picam2.capture_array()
            frame = cv2.resize(frame, (window_width, window_height))
            decoded_objects = pyzbar.decode(frame)
            for obj in decoded_objects:
                try:
                    qr_content = obj.data.decode("utf-8")
                    qr_json = json.loads(qr_content)
                    if isinstance(qr_json, dict):
                        qr_username = qr_json.get("username")
                        qr_token = qr_json.get("token")
                    else:
                        qr_username = None
                        qr_token = None

                    if qr_username and qr_token:
                        found = True
                        cv2.rectangle(frame, (obj.rect.left, obj.rect.top),
                                      (obj.rect.left + obj.rect.width, obj.rect.top + obj.rect.height),
                                      (255, 255, 255), 4)
                        cv2.putText(frame, "Scanned!", (obj.rect.left, obj.rect.top - 10),
                                    cv2.FONT_HERSHEY_SIMPLEX, 2, (255, 255, 255), 3)
                        cv2.imshow(win_name, frame)
                        cv2.waitKey(600)
                        break
                except json.JSONDecodeError:
                    qr_username = None
                    qr_token = None
                    continue
                except Exception:
                    qr_username = None
                    qr_token = None
                    continue

            cv2.imshow(win_name, frame)
            if cv2.waitKey(1) & 0xFF == ord('q') or found:
                break

        cv2.destroyAllWindows()
        picam2.close()

        if found and qr_username and qr_token:
            if validate_qr_login(qr_username, qr_token):
                show_main_menu()
            else:
                messagebox.showerror("QR Login Failed", "QR code not valid or not found in database.")
        elif not found:
            messagebox.showwarning("QR Not Found", "No valid QR code detected.")

    except Exception as e:
        try:
            cv2.destroyAllWindows()
        except:
            pass
        messagebox.showerror("QR Scan Error", f"Error scanning QR code:\n{e}")



def launch_stroke_detection():
    global current_user_id, current_username

    def run_detection_and_show_result():
        # Run main.py and capture its output for display on the result panel
        script_dir = os.path.dirname(__file__) if '__file__' in globals() else os.getcwd()
        stroke_main_path = os.path.join(script_dir, "main.py")
        if not os.path.exists(stroke_main_path):
            messagebox.showerror("File Error", "main.py (stroke detection) not found in the current directory.")
            return

        os.environ['CURRENT_USER_ID'] = str(current_user_id)
        os.environ['CURRENT_USERNAME'] = current_username
        try:
            with open('current_user.txt', 'w') as f:
                f.write(f"{current_user_id}\n{current_username}")
        except Exception as e:
            print(f"Warning: Could not save user info to file: {e}")

        try:
            # --- RUN DETECTION AND GET RESULT ---
            python_commands = ["python3", "python", sys.executable]
            output = ""
            for cmd in python_commands:
                try:
                    proc = subprocess.Popen(
                        [cmd, "main.py", str(current_user_id)],
                        cwd=script_dir,
                        stdout=subprocess.PIPE,
                        stderr=subprocess.STDOUT,
                        env=os.environ.copy(),
                        text=True
                    )
                    out, _ = proc.communicate()
                    output = out
                    break
                except (subprocess.CalledProcessError, FileNotFoundError):
                    continue
            show_result_page(output)
        except Exception as ex:
            show_result_page(f"Error running main.py:\n{ex}")

    # Hide main menu, show result after detection finishes
    main_menu_frame.pack_forget()
    result_frame.pack(fill="both", expand=True)
    result_label.config(
    text="Detecting stroke, please wait...",
    font=get_fullscreen_font(0.03)  # or whatever size you want (try 0.03)
    )
    result_text.config(text="")
    root.update()
    threading.Thread(target=run_detection_and_show_result, daemon=True).start()

def show_result_page(result):
    # --- Cleanly extract only what you need from the output ---
    username = current_username if current_username else "-"
    risk_level = "-"
    success_msg = "Data saved successfully into database"

    # Try to extract the risk level from the output
    # Assume your main.py prints something like "Risk Level: HIGH" or similar
    for line in result.splitlines():
        if "risk level" in line.lower():
            risk_level = line.split(":")[-1].strip().capitalize()
            break

    # Compose the clean result message
    clean_result = (
        f"Username: {username}\n\n"
        f"Risk Level: {risk_level}\n\n"
        f"{success_msg}"
    )

    result_label.config(
    text="Analytics Result",
    font=get_fullscreen_font(0.04)
    )

    result_text.config(text=clean_result)


def return_to_menu():
    result_frame.pack_forget()
    show_main_menu()

username_entry.bind('<Return>', on_enter_key)
password_entry.bind('<Return>', on_enter_key)
username_entry.focus()

def on_closing():
    root.destroy()

root.protocol("WM_DELETE_WINDOW", on_closing)
print("Ready.")
root.mainloop()
