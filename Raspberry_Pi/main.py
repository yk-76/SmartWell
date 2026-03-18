#!/usr/bin/env python3

import time
import datetime
import numpy as np
from PIL import Image
import subprocess
import os
import sys
import tflite_runtime.interpreter as tflite
from scipy.special import softmax
import warnings
import socket
import json
warnings.filterwarnings("ignore", category=UserWarning, module="numpy")


class StrokeAnalysisSystem:
    def __init__(self, model_path="stroke_model.tflite", user_id=None):
        """Initialize the stroke analysis system with user ID"""
        self.model_path = model_path
        self.user_id = user_id   # Use provided user_id or default
        self.interpreter = None
        self.input_details = None
        self.output_details = None
        self.load_model()
        
        print(f"Initialized Stroke Analysis System for User ID: {self.user_id}")
        
    def load_model(self):
        """Load the TensorFlow Lite model"""
        try:
            self.interpreter = tflite.Interpreter(model_path=self.model_path)
            self.interpreter.allocate_tensors()
            
            # Get input and output details
            self.input_details = self.interpreter.get_input_details()
            self.output_details = self.interpreter.get_output_details()
            
        except Exception as e:
            print(f"Error loading model: {e}")
            raise
    
    def capture_image_with_preview(self, output_path="captured_image.jpg"):
        """Capture image using libcamera with 10-second preview"""
        try:
            # Use libcamera-still with preview
            cmd = [
                "libcamera-still",
                "-t", "5000",  # 10 second preview
                "-o", output_path,
                "--width", "640",
                "--height", "480"
            ]
            
            # Run the command
            result = subprocess.run(cmd, capture_output=True, text=True)
            
            if result.returncode == 0:
                return output_path
            else:
                print(f"Error capturing image: {result.stderr}")
                return None
                
        except Exception as e:
            print(f"Camera error: {e}")
            return None
    
    def preprocess_image(self, image_path, method="teachable_machine"):
        """Preprocess image for the model with multiple options"""
        try:
            # Load image
            image = Image.open(image_path)
            
            # Get expected input shape and type from model
            input_shape = self.input_details[0]['shape']
            input_dtype = self.input_details[0]['dtype']
            height, width = input_shape[1], input_shape[2]
            
            # Convert to RGB if needed
            if image.mode != 'RGB':
                image = image.convert('RGB')
            
            # Resize image
            image = image.resize((width, height), Image.Resampling.LANCZOS)
            
            if method == "teachable_machine":
                # Teachable Machine style preprocessing ([-1,1], then uint8)
                image_array = np.array(image, dtype=np.float32)
                image_array = (image_array / 127.5) - 1.0
                
                if input_dtype == np.uint8:
                    image_array = ((image_array + 1.0) * 127.5).astype(np.uint8)
            
            elif method == "normalize_0_1":
                # Normalize pixels to [0,1]
                image_array = np.array(image, dtype=np.float32) / 255.0
            
            elif method == "normalize_m1_p1":
                # Normalize pixels to [-1,1]
                image_array = (np.array(image, dtype=np.float32) / 127.5) - 1.0
            
            elif method == "mean_std":
                # Normalize with mean/std for ImageNet models
                mean = np.array([0.485, 0.456, 0.406])
                std = np.array([0.229, 0.224, 0.225])
                image_array = np.array(image, dtype=np.float32) / 255.0
                image_array = (image_array - mean) / std
            
            elif method == "quantized_input":
                # Quantize input based on model input scale and zero point
                input_scale, input_zero_point = self.input_details[0]['quantization']
                image_array = np.array(image, dtype=np.float32) / 255.0
                image_array = image_array / input_scale + input_zero_point
                image_array = np.clip(image_array, 0, 255).astype(np.uint8)
            
            else:
                # Default: simple resize and convert to uint8
                image_array = np.array(image, dtype=np.uint8)
            
            # Add batch dimension
            image_array = np.expand_dims(image_array, axis=0)
            
            return image_array
        
        except Exception as e:
            print(f"Error preprocessing image: {e}")
            return None

    
    def analyze_image(self, image_array):
        """Run inference on the preprocessed image"""
        try:
            # Set input tensor
            self.interpreter.set_tensor(self.input_details[0]['index'], image_array)
            
            # Run inference
            self.interpreter.invoke()
            
            # Get output
            output_data = self.interpreter.get_tensor(self.output_details[0]['index'])
            
            return output_data
            
        except Exception as e:
            print(f"Error during analysis: {e}")
            return None
    
    def interpret_results(self, output_data, temperature=1.0, threshold=0.5):
        """
        Convert raw model output to human-readable result with confidence.
        Always returns a valid result, never an error string.
        """
        try:
            # Validate output shape safely
            if output_data.ndim == 2 and output_data.shape[1] >= 2:
                scale, zero_point = self.output_details[0].get('quantization', (1.0, 0))
                raw_output = output_data.astype(np.float32) * scale + zero_point

                logits = raw_output / temperature
                exp_logits = np.exp(logits - np.max(logits))
                probabilities = exp_logits / np.sum(exp_logits, axis=1, keepdims=True)
                probabilities = probabilities[0]

                predicted_class = np.argmax(probabilities)
                confidence = probabilities[predicted_class]

                class_names = ["HIGH RISK", "LOW RISK"]
                result = class_names[predicted_class]

                # Threshold to reduce false alarms
                if result == "HIGH RISK" and confidence < threshold:
                    result = "LOW RISK"
                    confidence = 1.0 - confidence

                return result, confidence

            else:
                return "LOW RISK", 0.0

        except Exception as e:
            return "LOW RISK", 0.0

    def get_local_ip(self):
        """Get the local IP address of the Raspberry Pi"""
        try:
            # Connect to a dummy address to get the local IP
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("0.0.0.0", 80))
            local_ip = s.getsockname()[0]
            s.close()
            return local_ip
        except Exception as e:
            print(f"Error getting local IP: {e}")
            return "127.0.0.1"

    def send_result_to_database(self, risk_level, confidence):
       
        php_script_path = "insert_data.php"  # PHP script in same folder
        
        try:
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            
            # Check if PHP script exists
            if not os.path.exists(php_script_path):
                print(f"PHP script not found: {php_script_path}")
                return
            
            print(f"Executing PHP script: {php_script_path}")
            print(f"Data: UserID={self.user_id}, riskLevel={risk_level}, detectedAt={timestamp}")
            
            # Create environment variables to pass data to PHP
            env = os.environ.copy()
            env.update({
                'USER_ID': self.user_id,  
                'RISK_LEVEL': risk_level,
                'DETECTED_AT': timestamp
            })
            
            # Execute PHP script
            result = subprocess.run(
                ['php', php_script_path],
                env=env,
                capture_output=True,
                text=True,
                timeout=30
            )
            
            # Check result
            if result.returncode == 0:
                output = result.stdout.strip()
                if "Success" in output:
                    print(f" Stroke analysis result saved successfully!")
                    print(f"  - UserID: {self.user_id}")
                    print(f"  - Risk Level: {risk_level}")
                    #print(f"  - Confidence: {confidence:.2%}")
                    print(f"  - Detected At: {timestamp}")
                    print(f"  - Device IP: {self.get_local_ip()}")
                    print(f"  - PHP Response: {output}")
                else:
                    print(f"Database error: {output}")
                    if result.stderr:
                        print(f"PHP Error: {result.stderr}")
            else:
                print(f"PHP script execution failed (return code: {result.returncode})")
                if result.stderr:
                    print(f"Error: {result.stderr}")
                if result.stdout:
                    print(f"Output: {result.stdout}")
                
        except subprocess.TimeoutExpired:
            print("PHP script execution timeout")
            
        except FileNotFoundError:
            print("PHP not found. Please install PHP on your Raspberry Pi:")
            print("  sudo apt update")
            print("  sudo apt install php-cli php-mysql")
            
        except Exception as e:
            print(f"✗ Error executing PHP script: {e}")
    
    def run_analysis(self, preprocessing_method="teachable_machine"):
        """Main function to run the complete analysis"""
        print("=" * 50)
        print("STROKE ANALYSIS SYSTEM")
        print(f"Current User: {self.user_id}")
        print("=" * 50)
        
        image_path = self.capture_image_with_preview()
        if not image_path:
            print("Failed to capture image. Exiting.")
            return
        
        if not os.path.exists(image_path):
            print(f"Image file {image_path} not found. Exiting.")
            return
        
        processed_image = self.preprocess_image(image_path, preprocessing_method)
        if processed_image is None:
            print("Failed to preprocess image. Exiting.")
            return
        
        output = self.analyze_image(processed_image)
        if output is None:
            print("Failed to analyze image. Exiting.")
            return
        
        result, confidence = self.interpret_results(output)
        
        print("\n" + "=" * 50)
        print("ANALYSIS RESULTS")
        print("=" * 50)
        print(f"User ID: {self.user_id}")
        print(f"Result: {result}")
        #print(f"Confidence: {confidence:.2%}")
        print("=" * 50)
        
        # Send result to database
        self.send_result_to_database(result, confidence)
        
        # Clean up captured image
        try:
            os.remove(image_path)
            print("\nTemporary image file cleaned up.")
        except:
            pass


def main():
    """Main function"""
    try:
        # Get user_id from multiple sources (in priority order)
        user_id = None
        
        # 1. Try command line arguments first
        if len(sys.argv) > 1:
            user_id = sys.argv[1]
            print(f"Using User ID from command line: {user_id}")
        
        # 2. Try environment variable
        elif 'CURRENT_USER_ID' in os.environ:
            user_id = os.environ['CURRENT_USER_ID']
            print(f"Using User ID from environment: {user_id}")
        
        # 3. Try reading from file
        elif os.path.exists('current_user.txt'):
            try:
                with open('current_user.txt', 'r') as f:
                    lines = f.read().strip().split('\n')
                    if len(lines) >= 1:
                        user_id = lines[0]
                        print(f"Using User ID from file: {user_id}")
            except Exception as e:
                print(f"Warning: Could not read user file: {e}")
        
        # 4. Fallback to default 
        if not user_id:
            user_id = "U0001"
            print(f" WARNING: Using default User ID: {user_id}")
        
        system = StrokeAnalysisSystem("stroke_model.tflite", user_id)
        
        system.run_analysis()
        
    except KeyboardInterrupt:
        print("\nSystem interrupted by user.")
    except Exception as e:
        print(f"System error: {e}")

if __name__ == "__main__":
    main()
