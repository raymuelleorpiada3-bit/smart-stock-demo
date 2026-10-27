# Upload and share this clean project on GitHub

The ZIP contains a GitHub-ready project. The **owner's working database is not in it**, because database exports include account records and password hashes. It contains `database/groupmate_clean_setup.sql` for groupmates to set up fresh accounts.

## Easiest: GitHub Desktop on Windows

1. Sign in to GitHub Desktop with your GitHub account. Extract this ZIP into a new folder separate from your working XAMPP app. Open the extracted folder and confirm you can see `README.md`, `.gitignore`, `database`, and `1st prog`. This is a prepared copy from the project shared earlier; if you changed code later on your Windows PC, copy those newer code files into the matching folders before publishing. Keep your personal `db_connection.php` and live SQL export out of the shared folder.
2. In GitHub Desktop click **File → New repository**. Name it `smart-stock-group-demo`, choose a local folder where you can find it, and click **Create repository**. Leave the new repository's optional README and Git ignore choices empty; the extracted project has its own.
3. In GitHub Desktop click **Repository → Show in Explorer**. Copy the **contents** of the extracted folder (including `.gitignore` and the `1st prog` folder) into the repository folder Explorer opened. Do not copy the ZIP itself or the owner's current SQL backup.
4. Return to GitHub Desktop. On the **Changes** tab check that `database/groupmate_clean_setup.sql` and the PHP/Python files appear. Your personal `db_connection.php`, a full database export, and `__pycache__` should not appear. Type `Initial Smart Stock demo` in **Summary**, then click **Commit to main**.
5. Click **Publish repository**. Keep **Keep this code private** checked, then click **Publish repository**. Click **Repository → View on GitHub** to verify that the browser shows `README.md`, `database`, and `1st prog`.
6. On GitHub open the repository's **Settings → Collaborators → Add people**. Invite each classmate by GitHub username and ask them to accept the invitation. Each person can then **Clone** the repo through GitHub Desktop, follow `GROUPMATE_SETUP.md`, create a separate branch for their task, commit, publish their branch, and open a pull request for review.

## Alternative: upload from a PowerShell terminal

1. Sign in to GitHub, choose **New repository**, enter a name such as `smart-stock-group-demo`, and set visibility to **Private**.
2. Leave the new repository **empty**: do not ask GitHub to generate a README or .gitignore because those files are already here.
3. Extract this ZIP to a separate folder on your Windows computer (do not extract it over your existing live app). In VS Code choose **File → Open Folder** and select the extracted folder containing `README.md`, `database`, and `1st prog`.
4. In **Terminal → New Terminal** (PowerShell) run each command below. Replace the example GitHub URL with the URL shown by *your new empty repository*:

```powershell
git --version
git init
git branch -M main
git add .
git status --short
git commit -m "Add clean Smart Stock demo and Stock History"
git remote add origin https://github.com/YOUR_USERNAME/smart-stock-group-demo.git
git push -u origin main
```

After `git add .`, look at `git status --short` **before committing**. You should see `database/groupmate_clean_setup.sql` and code files, but **not** `db_connection.php` or any private full-database export. If `git --version` fails, install Git or use GitHub Desktop before proceeding. If Git asks you to sign in on the first push, use its browser sign-in flow. Do not paste an account password into a code file.

To give your groupmates access, open the new repository on GitHub → **Settings → Collaborators → Add people**, select their GitHub usernames, and have them accept the invitations. You can then share the private repository's link.

## What each groupmate does

In a PowerShell window on their own Windows computer:

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs'
git clone https://github.com/YOUR_USERNAME/smart-stock-group-demo.git SmartStockDemo
Set-Location -LiteralPath '.\SmartStockDemo\1st prog'
Copy-Item -LiteralPath '.\php\config\db_connection.example.php' -Destination '.\php\config\db_connection.php'
```

Then follow [GROUPMATE_SETUP.md](GROUPMATE_SETUP.md): start Apache/MySQL, import the **clean** SQL into their own MySQL instance, register their own account, and start Python on their own computer. Every `127.0.0.1` URL is local to the device opening it.

## Your separate full backup

Keep the ZIP copy of your *currently working Windows* `1st prog` folder and a fresh phpMyAdmin **Custom** export of `inventory_restore_20260930` with structure, data, and stored routines on your backup drive. This private database backup preserves your actual owner account and demo sales; this GitHub package is a separate clean team demo.

Do not upload the private database export into either a public or private Git repository. If an older GitHub repo already has the previously exported SQL containing the deleted users, simply deleting that SQL file in a new commit does not erase prior commits. Use this **new** clean repo for teammates.
