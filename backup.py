# This module will perform a backup only if the current branch is "main".
import os 
import subprocess

FILENAME = "accounting_new_react_php.tar.gz"
BACKUP_COMMAND = rf'''del /f {FILENAME} & tar -cvzf {FILENAME} public/* src .eslintrc.cjs .gitignore backup.py index.html *.json *.ts & xcopy {FILENAME} "C:\Users\abstr\Google Drive\" /Y & mega-put {FILENAME} /source_codes/ && del /f {FILENAME}'''
GIT_COMMAND = "git branch --show-current"

def backup():
    if subprocess.check_output(GIT_COMMAND, False).decode('UTF-8').strip() == "main":
        os.system(BACKUP_COMMAND)
    else:
        print("Not on *main* branch!. Aborting Backup.")

backup()
