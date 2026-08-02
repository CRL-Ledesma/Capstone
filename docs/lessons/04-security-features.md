# Lesson 4 — How the System Protects Itself

This lesson walks through what actually stops someone from breaking into the system or messing with its data. Everything in here has been checked directly against the real code, not just described from memory — so if a panel opens the files, what's written here holds up.

One honest note before starting: an earlier version of the project claimed there was a math puzzle and a separate captcha service protecting the login page. Neither of those turned out to be real when checked against the actual code — only a leftover placeholder sits in a settings file, unused. So this lesson only describes what's genuinely there, since that's what someone could open and verify for themselves.

---

## Passwords are never actually stored

When someone creates an account, their password gets scrambled using a well-known one-way method before it's saved anywhere. What actually lands in the database is a long meaningless string of characters — not the real password, and not something that can be reversed back into it either. The only way to check a password later is to scramble a fresh attempt the same way and see if the two scrambled versions match exactly.

New passwords also have to meet a minimum standard before the system accepts them — at least 8 characters, no more than 18, with a mix of uppercase, lowercase, a number, and a special character. This stops someone from setting a password as weak as "1234."

If a panel asks how passwords are protected, the answer is simple: even someone with full access to the database can't read anyone's actual password, because the real password was never stored in the first place.

---

## Slowing down someone guessing passwords

Every time a login attempt fails, a counter goes up. After five wrong attempts in a row, that account gets locked out for five minutes. During the lockout, the error message deliberately doesn't say "too many attempts" right away for the first few tries — it just says the username or password is wrong, the same as always. That's on purpose. It stops someone from being able to tell whether they got the password wrong or just tripped the lockout, which would otherwise give away useful information.

There's also a short pause added after every failed attempt — long enough to meaningfully slow down a script trying thousands of passwords automatically, but far too short for a real person to ever notice while typing.

On top of that, every login attempt is also tracked separately by the IP address it came from, stored in the database. So even if someone clears their browser cookies to reset the attempt counter, their actual network address is still being watched.

---

## A trap that only bots fall into

The login form has one extra field that a real visitor will never see or touch. It's hidden from view completely, and it's also set up so it gets skipped by keyboard navigation and screen readers, meaning it never gets in the way of someone using accessibility tools either.

Bots that are scanning the internet and filling in every form they find, though, often fill in that hidden field too, since they can't tell it's meant to be invisible. The moment that field comes back with anything typed into it, the system knows it's dealing with a bot, logs it, and quietly drops the request without responding — the bot doesn't even get told anything went wrong. It just gets nothing back.

---

## Resetting a forgotten password requires more than just clicking a link

Most systems send a reset link by email and call it done. This one adds an extra step — after clicking the link, a six-digit code also has to be entered before the password can actually change. That code gets generated fresh each time and expires after five minutes.

The code is sent out through every channel that's actually set up — by text message and by email, if both are configured. Whoever's resetting the password gets five tries to enter the correct code before it expires and a new one has to be requested.

If the code can't actually be delivered for some reason, the system doesn't just fail — it shows the code directly on the screen instead, so the whole process can still be demonstrated during a presentation without needing a real phone or inbox on hand.

Here's something worth being upfront about, in that same honest spirit as the note at the top of this lesson: the email side of this used to go through a proper email-sending service, where the access key was kept safely in a separate settings file, away from the actual code. It was later switched to send through a personal Gmail account instead, which is a reasonable choice on its own — but the Gmail address and its password ended up typed directly into a source code file, instead of being kept in that separate settings file where it belongs. That means a real password is currently sitting in plain text inside a file that's part of the project. This is worth fixing before handing the project to anyone else — move both values into the settings file, and change that Gmail password, since it's already been sitting exposed in a file that could easily end up shared somewhere.

---

## Stopping forms from being faked

There's a specific kind of trick where a completely different, malicious website tries to get someone's browser to secretly submit a form on this system, using whatever session they're already logged into, without them realizing it happened. The defense against this is a hidden, unique code added to every form that changes something — adding a bill, editing a patient, creating a user. Before anything gets saved, that hidden code gets checked against what the system expects for that specific session. If it doesn't match exactly, the request gets rejected outright, even if the person looks fully logged in otherwise.

---

## Making a stolen login session less dangerous

A handful of settings work together here, and each one closes off a different way a login session could be abused. The session cannot be read by any script running on the page, which blocks a whole category of attacks where malicious code tries to steal it directly. A made-up session identifier gets rejected immediately, so nobody can plant one ahead of time and hijack it later. The session cookie won't get sent along when a request comes from a different website. Anyone inactive for more than eight hours gets logged out automatically. And every time someone successfully logs in, a completely fresh session identifier gets issued, so anything that existed before that login can never be reused afterward.

---

## Slowing down abuse of the background requests

Parts of the system that respond to background requests — the kind that happen automatically without a full page reload — keep track of how many requests are coming from each address within a short window of time. Going over that limit gets a request rejected with a message saying how long to wait before trying again. If that tracking system itself runs into a problem, the deliberate choice was to let requests through rather than block every single user — a tracking hiccup should never be able to take the whole system down for everyone at once.

---

## Keeping malicious data out of the database

Every single place in this system that talks to the database does it in a way that keeps whatever a user typed completely separate from the actual database command being run, rather than gluing pieces of text together directly — which is exactly the kind of shortcut that normally opens the door to attacks. As one real example, the reports section takes a month value straight from the web address someone typed in, but before that value ever gets used, it gets checked against a strict pattern for what a real month should look like. Anything that doesn't match that pattern gets quietly replaced with the current month instead. So even someone deliberately editing the address bar to try sneaking something harmful through that value would just get redirected to today's month before it ever reached a real database command.

---

## A record of who did what

Every important action in the system writes a permanent entry recording who did it, what the action was, which part of the system it happened in, which specific record it touched, and the address it came from. Successful logins, billing changes, changes to patient and treatment records, and changes to user accounts are all tracked this way, and that history can be looked back through later.

One honest gap worth knowing: failed login attempts and bot detections only get written to the server's own error log, not to that same permanent record everything else uses. Only a successful login creates an entry in that main history. So if a panel member asks to see a record of a failed login, the accurate answer is that it lives in the server's error log rather than the activity screen inside the app — better to know that distinction going in than to promise something that screen doesn't actually show.

---

## An honest summary, all in one place

Here's what's genuinely real and working right now: password scrambling, the five-attempts-then-lockout system, the hidden bot trap, the two-step password reset with a text and email code, the forged-form protection on every form that changes data, all the session protections described above, the background request limiting, and safe handling of every database command. Successful logins and changes to billing, patients, and user accounts are all recorded permanently.

What's not fully there yet: failed login attempts only reach the server's error log, not the same permanent record as everything else. The Gmail password used for sending reset codes is currently typed directly into a code file instead of living safely in the settings file, exactly as described above. And neither a math puzzle nor a separate captcha service is actually protecting the login page — only an unused placeholder exists in a settings file.

If either of those last two things — properly hiding that Gmail password, or folding failed login attempts into the same history as everything else — feels worth fixing before a final presentation, both are small, well-defined pieces of work rather than anything that would require rebuilding the system.

---

## Try it yourself

Pick a test account and type the wrong password into it five times in a row on purpose. Watch for the lockout message to appear, then wait five minutes and confirm it clears and lets you try again. Next, open the browser's developer tools on the login page, find that hidden field mentioned earlier, make it visible, type something into it, and submit the form — confirm nothing happens and no response comes back. Finally, ask whoever has server access to open the error log and confirm both of those things — the failed passwords and the bot attempt — actually got recorded there. Being able to demonstrate all of this live, and explain what's happening at each step, is a genuinely strong answer to "how do you stop someone from breaking in."