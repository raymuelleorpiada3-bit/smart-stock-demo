# Smart Stock: groupmate setup and commands

This is a **clean GitHub-ready demo copy** of the PHP app and Python service with Stock History. The included SQL creates an independent database named `inventory_group_demo`; it contains location lookup data and one demo store, with **no previous users, passwords, products, or sales**. Each groupmate sets it up on their *own computer*. See `GITHUB_UPLOAD.md` for sharing the repo.

## 1. Unzip and place the code

Install XAMPP (Apache and MySQL/MariaDB) and Python first. Clone the GitHub repo as `C:\xampp\htdocs\SmartStockDemo` or extract this ZIP there. The `1st prog` folder and its child folders should stay together:

`C:\xampp\htdocs\SmartStockDemo\1st prog\`

If you choose another location, use that location when opening the folder and browser URL below. Start **Apache** and **MySQL** in the XAMPP Control Panel.

## 2. Import the clean demo database

Open `http://localhost/phpmyadmin/`. Click the **Server: 127.0.0.1** entry near the top (to avoid choosing the existing production database), then **Import**. Select `database/groupmate_clean_setup.sql` from this ZIP and click **Go**. It creates `inventory_group_demo`, its tables, the registration/login stored procedures, required geography lists, product barcode support, and sales columns. Allow it to complete.

Do this **only on a groupmate's fresh demo installation**. Reimporting this SQL into an existing `inventory_group_demo` drops the current tables and their data. Do not run it on the owner's working `inventory_restore_20260930` database. Do not import an old GitHub database export with deleted users. This project does not require an additional stock history SQL migration.

Open the `1st prog` folder in VS Code and open a PowerShell terminal there. Copy the safe configuration example, then edit the new local file if your MySQL credentials differ:

```powershell
Copy-Item -LiteralPath '.\php\config\db_connection.example.php' -Destination '.\php\config\db_connection.php'
```

The example selects `inventory_group_demo` and uses the usual local XAMPP username `root` with an empty password. The real `db_connection.php` stays **out of GitHub** through `.gitignore`.

## 3. Sign up and make a demo owner

Visit `http://localhost/SmartStockDemo/1st%20prog/`. Click **Create one here**, fill out the form, pick a province/municipality/barangay, and choose the existing **Demo Store**. Register with your **own** email and a new password; no account is included in this package. Then sign in. The new account starts as a **clerk**.

If your team needs one owner for Product Management, open phpMyAdmin, click `inventory_group_demo`, click **SQL**, and run this statement after replacing the example email with the one you just registered:

```sql
UPDATE users AS u
JOIN credential AS c ON c.users_id = u.users_id
SET u.users_role = 'owner'
WHERE c.cre_username = 'your-own-email@example.com';
```

Check that phpMyAdmin says **1 row affected**. Log out and sign back in so your session displays **Owner**. Other groupmates can register separate clerk accounts without sharing passwords.

## 4. Start the Python service for sales forecasts

In VS Code choose **File → Open Folder** and open your `1st prog` folder (not its parent). Open **Terminal → New Terminal**. In the PowerShell terminal run each line separately:

```powershell
Set-Location -LiteralPath '.\fastapi-service'
python -m pip install -r requirements.txt
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8000
```

Leave that terminal running. In a browser **on the same computer**, open `http://127.0.0.1:8000/`: it should say `Smart Stock monitoring API is running`. Then open `http://127.0.0.1:8000/docs`. PHP sends product data to `127.0.0.1:8000/analyze`; each teammate running a separate installation needs their **own** Python process. Stock History and completed sales work with Apache/MySQL; the Python process provides the 30-day baseline forecast. This is a baseline, not a trained AI model.

If `/docs` says **site can't be reached**, check the terminal for `Application startup complete`. If you get `Could not import module main`, run `Get-Location` and `Get-ChildItem main.py` in PowerShell: the terminal should be inside `fastapi-service`, next to `main.py`. If port 8000 is **already in use**, stop the old service with Ctrl+C in its terminal, then try again. If `/` works but has a different message, another app may be using port 8000. `127.0.0.1` on a phone or another PC points to that **other device**, not the computer running your service.

## 5. Try the inventory flow

As owner, add a product with a unique barcode in **Product Management**, e.g. `DEMOHISTORY01`, initial quantity **10**. Open **Stock History**: the initial `+10` row and current stock 10 should show. Increase it by 5 and write a reason; record a completed sale of 2 in **Sales Transactions**; reduce it by 1. Stock History should show the changes and current stock **12**. You can filter history by product/date and, as owner, select the store view. Scanner input by itself does not make a sale; click **Complete Sale** after checkout.

Products with sales or stock history are kept in the database so the history keeps its meaning; Product Management will show **History retained** rather than a Delete action. Old stock events made before installing Stock History cannot be inferred from current quantities.

Stock History also keeps the user who made each change. Once an account has recorded movements, keep its user row so the audit record has a valid author.

## Back up the owner's *current* working copy separately

This groupmate package is a **clean demonstration**, not an exact export of the owner's Windows database. To preserve the actual work so far, on the owner's PC:

1. Copy/ZIP the **currently working** `1st prog` folder, including `fastapi-service`, `php`, `html`, `css`, and `js`, after applying the Stock History update.
2. In phpMyAdmin select **`inventory_restore_20260930`** (the actual working database), click **Export**, choose **Custom**, include **structure and data** for all tables **and stored procedures/routines**, and save the SQL file on the backup drive.
3. Keep this live SQL export private: it contains user account data, including password hashes. For teammates, use this clean demo package and let each person register their own account.

The older SQL snapshots are from before the repaired database, current owner, and demo sales. They cannot substitute for a fresh export of the owner's working database.
