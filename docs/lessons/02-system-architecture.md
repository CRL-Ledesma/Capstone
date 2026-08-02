# Lesson 2 — How the System is Organized

This lesson explains how the project is structured and why it works the way it does. You don't need to memorize all of it, but understanding the structure means you can find anything quickly, and explain it clearly if a panel asks.

---

## Why the files are split up the way they are

Open the project folder and you'll see folders like modules, includes, assets, and database. Each one has a specific job.

The modules folder is where the actual features live — patients, appointments, billing, treatments, and so on. Every feature gets its own subfolder. If you want to find where the code for adding a new patient is, you open modules, then patients, then add.php.

The includes folder holds shared code that every page uses. Things like connecting to the database, checking if someone is logged in, and the sidebar that appears on every page — those live here. Instead of writing that same code in every single file, it's written once in includes and every page just loads it from there.

The assets folder holds styling, scripts, and images — the visual stuff.

The database folder holds one SQL file that builds the entire database from scratch when you import it.

---

## What happens every time you open a page

Every protected page in this system starts with the exact same three lines before doing anything else.

The first line loads the configuration — things like the app name, the base URL, error logging settings, and security headers that get sent with every response.

The second line connects to the database and loads a set of helper functions used everywhere — things like checking a security token on form submissions, writing to the activity log, and rate limiting.

The third line checks whether you're actually logged in. If you're not, it sends you straight to the login page. If your session has been inactive for more than eight hours, it logs you out automatically.

Because every page does this in the same order, there's only one place to change something that should affect the whole system. If you wanted to change the session timeout from eight hours to four, that's one line in one file, not something you'd have to hunt through every page to fix.

---

## The pattern most features follow

Open the patients folder inside modules and you'll see four files: list.php, add.php, edit.php, and view.php. Open the appointments folder and you'll see roughly the same four files. Open billing — same thing.

This is the pattern. List shows you all the records with search and filters. Add has the form to create a new one. Edit has the form to change an existing one. View shows one record in full detail with all its related information.

Once you understand how the patients module works, you already know where to look in any other module. The search logic is always in list.php. The form validation is always at the top of add.php and edit.php. The activity log call is always at the end of the save block.

Not every feature fits this exact shape. The settings folder is a good example — there's no list of settings to browse, so there's no list.php. Instead there are purpose-built screens: one for downloading a database backup, one for setting the clinic's name and logo, and one for a logged-in user to update their own profile. The shape changed because the job changed, but the foundation underneath is exactly the same as everywhere else.

---

## Why the page doesn't fully reload when you click a link

Click any link in the sidebar and watch carefully — the sidebar and header stay completely still. Only the content area in the middle changes. That's not how a normal website works, and it's not an accident.

There's a script running in the background that catches your click before the browser navigates away. It fetches the new page's content quietly behind the scenes, then swaps out just the middle section while leaving everything else untouched. The browser's address bar still updates, so the back button still works.

The reason this matters is speed and polish. Full page reloads make the sidebar flash every time you navigate. This approach feels more like an app than a website. It's also why the system has to clean up things like charts and popups before swapping in new content — if it didn't, leftover chart objects from the previous page would pile up in memory the longer you use the system.

---

## The dark mode detail worth knowing

Dark mode is saved in the browser so it's remembered the next time you come back. The problem with most dark mode implementations is a white flash — the page loads in light mode for a split second and then switches to dark. It looks amateur.

The fix here is a tiny script that runs before any CSS even loads. It checks the saved preference immediately and applies the dark theme before anything else happens on the page. So by the time the page becomes visible, it's already in the right mode. No flash.

If a panel asks how dark mode was implemented without the white flash, that's the explanation — it reads the preference before the stylesheet even loads.

---

## Try it yourself

Open VS Code and find the modules/doctors folder. Without any help, answer these three questions just by reading the files:

Where does it check that only an admin can access the doctor management pages?

Where does it validate the form data before saving a new doctor?

Where does it write to the activity log to record that a change was made?

Finding all three means you understand the pattern. If one of them is missing from the doctors module, that's actually a real gap worth fixing.