# Machine Coding Interview Questions — Reference List

A catalog of commonly asked "machine coding round" problems, grouped by shape. See the note at the bottom on where this interview format actually shows up before you over-invest in it.

---

## 1. OOD / system simulation (no DB, no server, no framework)

In-memory only — a `main()` or a test harness drives your classes. The evaluation is entirely about class design, SOLID principles, and how easily a new requirement could be bolted on without rewriting existing code.

- Parking Lot System (multiple floors, vehicle types, pricing strategies)
- Elevator System (multiple elevators, scheduling strategy)
- ATM Machine
- Vending Machine
- Splitwise / expense-sharing with debt simplification
- BookMyShow / movie ticket booking (seat locking, concurrent booking)
- Library Management System
- Hotel / Car Rental booking system
- Ride-sharing system (simplified Uber/Ola — matching, pricing)
- Food delivery system (simplified Swiggy/Zomato — restaurant, menu, order, delivery partner)
- Meeting Scheduler / calendar with conflict detection
- Chess / Tic-Tac-Toe / Snake and Ladder (game state machines)
- Airline / flight booking system
- Inventory Management System
- Shopping cart + checkout (discounts, coupons, pricing strategies)
- Logging framework (levels, multiple sinks/appenders)
- Notification system (observer pattern, multiple channels)
- Job/Task Scheduler with priorities and retries
- Cricket/sports scoreboard simulation

**What's actually being tested:** interface design, use of design patterns (Strategy, Observer, Factory, State) where they genuinely fit, extensibility, and whether your code degrades gracefully when the interviewer adds a new requirement mid-round.

---

## 2. Full-stack / API feature builds (DB + backend, sometimes a frontend)

You're asked to make something *run* — real HTTP endpoints, a real (or in-memory-stand-in) data store, sometimes a minimal UI.

- URL shortener (bit.ly clone)
- Login + signup + **password reset flow** (the example from this conversation)
- Auth system with roles/permissions (RBAC)
- Todo/task manager (CRUD)
- Pastebin clone
- Polling / voting app
- Basic chat app (bonus points for WebSockets)
- Twitter-like feed (post, follow, timeline generation)
- Instagram-like feed (posts, likes, comments)
- Leaderboard system (rankings, real-time updates)
- Rate limiter implemented as Express/Fastify middleware
- File upload service (abstracted storage layer)
- Autocomplete/search service (Trie or prefix index backed)
- E-commerce cart + checkout API

**What's actually being tested:** correct schema design, sane API contracts, security instincts (e.g., not leaking whether an email exists, hashing tokens/passwords, expiry/single-use on sensitive tokens), and knowing when to stop gold-plating (skip real email/SMS delivery, skip full ORM migrations, use in-memory stores) so you have a working demo inside the time limit.

**Java specifically uses Spring Boot here, and that's expected, not overkill.** See §4 below — unlike the NestJS-on-serverless mismatch discussed earlier for a real production app, Spring Boot is the idiomatic default for Java web backends, not an optional heavy layer on top of a simpler one. A Java candidate skipping it in favor of raw `HttpServer` would read as *not* knowing the standard toolchain, the opposite of the signal you want to send.

---

## 3. "Build the infra yourself" — data structure / systems primitives

No framework at all, sometimes not even HTTP — you're implementing something that a library would normally give you, to prove you understand what's underneath it.

- LRU Cache (design + implement, not just `Map` + eviction)
- Rate Limiter (token bucket, sliding window, fixed window — often asked to implement more than one)
- Circuit Breaker
- Thread-safe bounded queue / producer-consumer
- Pub/Sub system (in-memory event bus)
- In-memory key-value store with basic indexing (mini Redis)
- In-memory file system (folders, files, path resolution)
- Trie for autocomplete/prefix search

**What's actually being tested:** whether you can reason about concurrency, eviction, and time/space complexity without reaching for a library that hides the hard part.

---

## 4. Languages, frameworks, and the CoderPad/CodePair angle

**Languages seen most often:** Java, Python, and JavaScript/TypeScript dominate; C++ shows up (especially for Category 1, where explicit OOP syntax and manual memory/concurrency reasoning are part of the point); Go appears occasionally at companies with a Go backend.

**The framework choice tracks the *category*, not a blanket "avoid frameworks" rule:**

- **Category 1 (OOD/simulation)** — no framework in any language. Run in a plain pad (CoderPad's bare "Java"/"Python"/"C++" template, no live server), because there's no HTTP layer to stand up in the first place.
- **Category 2 (API/full-stack builds)** — the framework is whatever's idiomatic for that language's web stack, because the round is explicitly testing "can you build a real backend," not "can you avoid dependencies":
  - Node → Express or Fastify
  - Python → Flask, FastAPI, or Django REST
  - **Java → Spring Boot** — this is the default, not a heavyweight opt-in. Interviewers evaluating Java backend candidates generally *expect* Spring familiarity (`@RestController`, `@Service`, `@Repository`, DTOs) the same way they'd expect an Express candidate to know middleware.
  - Ruby → Rails

**Tools like CoderPad and HackerEarth CodePair have dedicated "Sandbox" environments for exactly this** — a live running server with a browser preview, not just a scrollable text pad — and Java + Spring Boot is one of the officially supported combos alongside Node + Express and Python + Django/Flask. That's a strong signal by itself: companies picking a Java machine-coding format are choosing Spring Boot deliberately, not tolerating it.

**Moving fast inside a timed Spring Boot round**, the common shortcuts that keep you inside the time limit without looking like you're cutting corners:
- **H2** (in-memory database) via Spring Data JPA instead of provisioning a real Postgres/MySQL — zero setup, real repository-layer code.
- **Lombok** (`@Data`, `@AllArgsConstructor`, etc.) to cut getter/setter/constructor boilerplate so your visible code is domain logic, not scaffolding.
- Standard three-layer split — `Controller → Service → Repository` — even under time pressure; collapsing layers reads as not knowing the convention, not as "moving fast."

## 5. Where this format actually shows up

(Carried over from earlier discussion, worth keeping next to the list.)

- **Indian product companies and unicorns** (Flipkart, Swiggy, Zomato, Razorpay, CRED, PhonePe, Meesho, Ola, Paytm, Uber's India org, most funded startups there) — a **standard, dedicated round**, distinct from DSA and system design, especially for SDE-2/SDE-3 and backend/full-stack roles. Java is very heavily represented in this pool specifically.
- **Startups generally, worldwide** — common as either a live "build this feature" session or an async take-home with a follow-up review call; format varies a lot by company.
- **Traditional large US tech (Google, Meta, Amazon, Microsoft, Apple)** — rare as a distinct round; these loops lean on DSA + system design + behavioral instead.

## 6. Prep notes

- **Match the framework weight to the round type, not a fixed "lighter is better" rule.** Category 1 → no framework at all, any language. Category 2 → the idiomatic web framework for whatever language you're using — Express/Fastify for Node, Flask/FastAPI for Python, **Spring Boot for Java** — because that's what's expected, not because it's the minimal option. (This is a different judgment from choosing a framework for a real production app, e.g. the NestJS-vs-Next.js tradeoffs discussed earlier — there the question was deployment fit; here it's "does this look like idiomatic code to someone hiring for this stack.")
- **Say the tradeoffs out loud.** "I'm using an in-memory `Map`/H2 instead of a real DB to save setup time — in production this is a table with this schema" reads as strong signal even though it's a shortcut.
- **Time-box ruthlessly.** Most rounds are 60–120 minutes. Get a working end-to-end path first (even a naive one), then layer in edge cases and patterns — a fully working naive solution beats an elegant, half-finished one.
