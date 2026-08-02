# Lesson 3 — How the Database is Designed

This lesson walks through how the data is organized — what each table stores and how they all connect. You don't need to be a database expert to follow this. Think of it like understanding the layout of a filing cabinet before you start using it.

---

## The four tables that drive everything

Almost every action in the system touches one of four tables: patients, appointments, dental_records, and bills. Understanding these four is enough to understand the whole system.

The patients table stores one row per patient — their name, birthday, blood type, allergies, emergency contact, and medical history. Every other table points back to this one.

The appointments table stores one row per visit, whether it was booked in advance or a walk-in. It records which patient, which doctor, which service, and what date and time. The status column tracks where that visit is in its lifecycle — pending, confirmed, completed, cancelled, or no-show.

The dental_records table stores what actually happened during a visit. The doctor's diagnosis, what treatment was done, which teeth were involved, what condition they were in, any materials used, medications prescribed, and notes for the next visit. A dental record always links back to a specific appointment.

The bills table is the money side. It records how much was owed, how much was actually paid, when, and how — cash, GCash, or bank transfer. It also links back to the appointment it came from.

So a patient's entire story flows in one direction: patient books appointment, appointment gets completed, doctor fills in the dental record, bill gets created. Those four tables in that order tell the whole story of a single visit.

---

## How the tables connect to each other and why it matters

The appointments table has a column called patient_id. That column stores the ID number of the patient the appointment belongs to. The database is set up so that if a patient record is ever permanently deleted, every appointment linked to them gets deleted too. That makes sense — an appointment without a patient attached to it is meaningless data.

But doctors and services work differently. If a doctor is removed from the system, the appointments they were part of don't get deleted — the doctor link just gets cleared. The appointment history stays. Same with services. This is intentional because the clinic's history of what happened should survive even when the list of current doctors or services changes.

The reason this matters for a panel question: if someone asks how the system handles deleted data, the answer is that the database rules handle it automatically. It's not something the PHP code has to manually clean up, the database engine enforces it the moment a delete happens.

---

## Why patients never get permanently deleted

The patients table has a column called is_active. The original design was that instead of ever deleting a patient, the system would just flip that column to false, hiding them from the normal list but keeping all their records intact. Medical records can't legally just disappear, so this approach — called a soft delete — is the right one for a clinic system.

Here's the honest part worth knowing before a panel asks: the is_active column and the database rules protecting it are still there and still enforced. But the screen that let someone actually archive or restore a patient was removed from the current build. Right now every patient shows as active because there's no way in the interface to change it. If a panel asks about archiving patients, the accurate answer is that the database is already set up for it, and putting the screen back would be a straightforward addition — the hard part (the data design) is already done.

---

## The tables that support everything else

Beyond the four core tables, there are supporting tables that power specific parts of the interface.

The users table stores the login accounts for staff and admin. It's separate from patients — clinic staff are not the same as patient records.

The services table is the price list. Each service has a name, a price, and a duration. When a bill is created, it links to whichever service was performed.

The doctors table stores doctor profiles, including leave dates. The booking system reads those leave dates to know which doctors are unavailable on a given day.

The schedules table stores the clinic's weekly hours — what time they open and close each day of the week. The walk-in system reads this to figure out the next available slot.

The blocked_dates table stores one-off closed days like holidays. When a patient tries to book on a blocked date, the system stops them.

The notifications table powers the bell icon in the header. Every alert is one row in this table with a read/unread status.

The audit_logs table records every important action — who did it, what they did, which record they touched, and the IP address. This is covered more in the security lesson.

---

## Two tables that exist but aren't used yet

There are two tables in the database that no part of the current system reads or writes to. One was set up for a future feature to track dental supply inventory. The other was set up for future system-wide settings. Both tables exist, both are empty, and nothing in the code touches them yet.

If a panel member opens the database and asks about those tables, the honest answer is that they were built ahead of time for planned features that aren't part of this version yet. That's a completely normal thing in real software development — the structure gets set up before the feature is built. Saying that confidently is a much better answer than being caught off guard or pretending the tables aren't there.

---

## Try it yourself

Open HeidiSQL and connect to the cap database. Click on the appointments table and look at its columns. Find the one that links it to the patients table and the one that links it to the services table. Then try to figure out: what would happen to an appointment record if the service it was linked to got deleted? The answer is in the column definition — look for the words ON DELETE and read what comes after them. Being able to explain that live is a solid answer to a database design question.