@echo off
cd /d "%~dp0"
echo Installing Python dependencies...
py -3 -m pip install -r requirements.txt 2>nul || python -m pip install -r requirements.txt
echo Starting EchoShield AI on http://127.0.0.1:5000
py -3 app.py 2>nul || python app.py
pause
