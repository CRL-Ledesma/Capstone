# Lesson 1 — Getting It Running

This is the first thing to read. Whether you're setting this up on your own laptop, helping a groupmate get it running, or handing it over to an actual clinic — start here.

---

## What you actually need to install

You only need one program: **Laragon**. That's it. Laragon bundles a web server and a database together, so you don't need to install them separately. If your machine already has XAMPP instead, that works too — they do the same job.

Download Laragon from laragon.org and install it like any normal program. Once it's installed, open it and you'll see a Start All button. That's what you'll click every time you want to use the system.

One small thing to know: the part of the system that sends one-time codes by email uses a small helper package. If you got this project as a zip file, that package is already inside the folder and you don't need to do anything. If someone sent you the project through Git (version control), that package won't be included — you'd need to install something called Composer and run it inside the project folder first. But if you're just using a zip, forget Composer exists.

---

## Setting it up, step by step

Follow these in order. Don't skip ahead.

**Step 1 — Put the project folder in the right place.**
Open your C drive, then the laragon folder, then the www folder inside it. Copy the entire project folder in there. The path should look like this: C:\laragon\www\cap. The folder name cap is what becomes part of the web address you'll type later.

**Step 2 — Start Laragon.**
Open Laragon and click Start All. You should see Apache and MySQL both turn green. If they don't start, make sure nothing else on your computer is using port 80 or port 3306 — sometimes Skype or another program blocks it.

**Step 3 — Create the database.**
Click the Database button in Laragon, or open HeidiSQL from the Start menu. Connect using the default credentials (root, no password). Create a new database and name it cap — the name has to match exactly. Then find the database/cap.sql file inside the project folder, open it or import it into HeidiSQL, and run it. This builds every table and puts in sample patients, doctors, and services so the demo isn't empty when you first open it.

**Step 4 — Set up the environment file.**
Open the project folder and look for a file called .env.example. Make a copy of it and rename the copy to just .env — remove the word example. Open it with Notepad or VS Code. The default values inside already match Laragon's standard setup, so you usually don't need to change anything. Just having the file there is enough.

**Step 5 — Open it in the browser.**
Go to http://localhost/cap in your browser. You should see the login page. If you see a database error instead, go back to step 2 and make sure MySQL is actually running.

---

## Logging in for the first time

After the database is imported, two accounts already exist. The admin account username is admin and the password is password. There's also a staff account with the same password. These are placeholder accounts for testing — change the passwords before any real patient data goes into the system.

---

## If something goes wrong

The most common problems and what to do:

The page doesn't load at all — Laragon isn't running. Open it and click Start All.

You get a database error on the page — either MySQL isn't running, or the .env file is missing, or you haven't imported cap.sql yet. Check all three.

The page loads but login doesn't work — make sure you typed admin and password exactly, no capital letters, no extra spaces.

Something looks broken after you made a change — open HeidiSQL, drop the cap database, create it again, and re-import cap.sql. That resets everything back to the starting point.

---

## Putting it on a real server someday

Right now this runs on your laptop. If the clinic ever wants it on a real server so it's accessible from anywhere, the project is already set up for that — there's a Dockerfile included, which means it can run on any cloud host that supports containers (like Railway, Render, or a standard VPS). You'd build it and run it with two commands. A clinic's IT person or the hosting provider can handle this part — you just hand them the project folder and the .env file with real database credentials.

---

## Before you hand it off to a real clinic

When it's time to give this to an actual clinic to use, go through this list in order:

First, import the database on their server and get the system running using the steps above.

Second, log in as admin and go to Settings, then Clinic Settings. Fill in the clinic's real name, address, phone number, email, and upload their logo if they have one. This is important — those details appear on every printed document the system produces: appointment slips, receipts, prescriptions, dental records, and certificates. If you skip this, every printout will say DentalCare Clinic instead of their actual name.

Third, change the admin password immediately. The default password password is publicly known, so leaving it as-is means anyone who knows the URL can log in.

Fourth, add the real doctors under the Doctors section, the real services and prices under Services, and the clinic's actual hours under Schedule.

Fifth, delete the sample patients that came with the demo data so the clinic's real patient records start clean.

After those five things, the system is ready for real use.

---

## Try it yourself before demo day

Do a full dry run on a different machine — a groupmate's laptop works. Start with a fresh Laragon install and nothing else. Follow this lesson from Step 1 without any help. If you can get from a blank laptop to the dashboard login in under 15 minutes, the setup is solid. If you get stuck, that's the step to practice explaining, because a panel might ask you to walk through it live.