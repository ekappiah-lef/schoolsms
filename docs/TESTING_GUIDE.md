# Testing guide — one role at a time

Test server: **https://98-81-86-52.sslip.io** (after the HTTPS update; until then http://98.81.86.52).
Locally: http://localhost:8000.

Email and SMS are in **demo mode**: only the contacts in `NOTICE_ALLOWLIST` (your own email and
number, used by the demo family *Emmanuel Appiah*) really receive anything. Everyone else shows as
"held back (demo mode)". MTN MoMo runs in **test mode** (no real money; numbers ending in 0 are declined).

## Logins

| Role | Username | Password | What they should see |
| --- | --- | --- | --- |
| Super admin | `cj` | `cj` | Everything, incl. Settings and Result pins |
| Admin | `admin` | `cj` | Everything except Settings; approves messages |
| Academic admin | `academic` | `cj` | Teachers (add/edit teachers only), students, exams, results, timetables, attendance, classes, Messages (need approval). No finance, other users or Settings |
| Accountant | `accountant` | `cj` | Finance only; cannot delete income/expenses |
| Class teacher (Class 6 Gold) | `teacher` | `cj` | Only Class 6 Gold students; marks for all Class 6 Gold subjects; attendance for Class 6 Gold |
| Subject teacher (JHS Maths) | `LTS/STAFF/2023/03/6589` | `teacher` | No student profiles; marks for Mathematics in JHS 1–3 only |
| Parent (your family) | `parent` | `cj` | Efua and Ethan Appiah, their fees and results |
| Student | `student` | `cj` | Own results and timetable |

### Families with balances brought forward from earlier terms

| Child | Class | Parent login (password `parent`) | Brought forward | Total due now |
| --- | --- | --- | --- | --- |
| Adelaide Afua Annan | JHS 2 Gold | `LTS/PARENT/2022/08/9669` | 31,724 (15 old bills) | 39,064 |
| Eric Essien | Class 2 Gold | `LTS/PARENT/2022/08/8465` | 23,500 | 26,420 |
| Elijah Bonsu | KG 1 Gold | `LTS/PARENT/2024/08/1413` | 14,200 | 14,340 |
| Edem Kwame Asante | Class 5 Gold | `LTS/PARENT/2022/08/7836` | 10,275 | 12,285 |
| Ama Serwaa Gyamfi | JHS 1 Gold | `LTS/PARENT/2022/08/9417` | 9,810 | 20,355 |
| Efua Appiah (your family) | JHS 1 Gold | `parent` / `cj` | 8,605 | 12,405 |
| Ethan Appiah (your family) | Class 4 Gold | `parent` / `cj` | 3,160 | 8,270 |

99 students in the demo have a balance brought forward. As admin or accountant, open **Student
payments → class → student** to see the invoice with the "Balance brought forward" lines.

## Checklist by role

### 1. Admin (`admin`)
1. **Dashboard** — four plain cards, Class Enrolment Monitor (dropdown), Finance Overview.
2. **Finance dashboard** — opens on 2026 – 2027: Total invoiced, Money received, Expenses, Year balance; **How fees were paid** (Cash, MTN MoMo, bank…).
3. **Fee breakdown** — Term by term: open *Academic Period*, expand years, tick terms → the table shows them with a total; **Columns** to add figures.
4. **Student payments → JHS 2 → Adelaide Afua Annan** — Invoice with balance brought forward; **Send to parent**; **MTN MoMo → Send prompt to phone** (test mode: enter 024 123 4567, amount → approved after ~8 s, applied to the oldest bill). Record a cash payment and choose the **method**.
5. **Send invoices to class** on Student payments.
6. **Income & expenses** — delete an entry (allowed for admin).
7. **Messages** — approve or reject what the academic admin submitted.
8. **Users → Add user → type Academic Admin** to create another academic admin.

### 2. Academic admin (`academic`)
1. Sidebar: no Finance, no Settings; **Teachers** instead of Users. Add a teacher: only "Teacher" can be chosen.
2. **Students → a student → Fees tab**: sees what is owed (no payment buttons).
3. **Admit student**: type a new nationality/state/LGA (“Add …”); **Take photo** (webcam on https/localhost).
4. **Examinations → Results tools → Tabulation sheet → Email reports to class**.
5. **Attendance** — any class.
6. **Messages** — send to *Parents of a class*: it shows “Submitted for approval”. Log in as admin to approve.

### 3. Accountant (`accountant`)
1. **Income & expenses** — no delete button; can still edit.
2. **Student payments** — record payments with a method (Cash, MTN MoMo, Telecel Cash…); MoMo prompt.
3. **Finance dashboard / Fee breakdown / Ledger**.

### 4. Class teacher (`teacher`)
1. **Students** — only 6 students (Class 6 Gold). Opening a Class 6 Diamond student is refused.
2. **Marks entry** — only Class 6 → Gold, every subject.
3. **Attendance** — Class 6 Gold: mark someone Absent → **Save register** → **Send absence alerts**.
4. **A student's results → Email to parent**.

### 5. Subject teacher (`LTS/STAFF/2023/03/6589`, password `teacher`)
1. **Students** — none (not a class teacher).
2. **Marks entry** — JHS 1/2/3, Mathematics only.

### 6. Parent (`parent` / `cj`, or a parent above with password `parent`)
1. **My children** — balances; **Fees statement** opens the online statement.
2. On the statement: the invoice with balance brought forward and **Pay with MTN MoMo** (test mode).
3. **Results** for each child.

### 7. Student (`student`)
1. **My marksheet**, **Timetable**.

## Needs your accounts before going live
* **MTN MoMo** — sandbox keys from https://momodeveloper.mtn.com (Collections subscription key, API user, API key), then MTN Ghana production keys. Set `MOMO_DRIVER=sandbox|production` and the `MOMO_*` values.
* **WhatsApp** — a WhatsApp Business account with the school number, display name “LEF School”, an approved template `school_message` (“Message from LEF School: {{1}}”), and a permanent access token. Set `WHATSAPP_ENABLED=true` and the `WHATSAPP_*` values.
