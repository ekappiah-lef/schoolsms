# Finance checks and audit guide

A guide for the school owner, the accountant and an outside auditor: how the system counts money, where to
find each figure, and the checks to run each term so that **every cedi billed, received and spent ties up**.

Figures in the examples are from the demo data (6 October 2026).

---

## 1. How the system counts money

Every page uses the same rules, so the same period always gives the same numbers.

| Rule | What it means |
| --- | --- |
| **Three terms cover the whole year with no gaps** | Term 1: 1 Aug – 31 Dec · Term 2: 1 Jan – 30 Apr · Term 3: 1 May – 31 Jul. Every bill, payment, income and expense falls in exactly one term. |
| **Each term starts from zero** | A term's balance = money received in the term − expenses in the term. Nothing is carried in from the term before. |
| **School fees belong to the term on the bill** | The term chosen in Fee setup. Invoiced = fee − discount. |
| **Services are a third per term** | Feeding, bus and clubs are billed once for the year: a third belongs to each term (any odd cedi goes to term 1). Payments on them settle term 1 first, then 2, then 3. |
| **Shop sales belong to the term they were sold in** | Uniforms, books, etc. |
| **Money received belongs to the day it was paid** | Old fees paid this term count as money received **this term**. |
| **Payments go to the oldest debt first** | A payment (cash, MoMo prompt, etc.) clears the oldest unpaid bill first. |
| **Four modes of payment** | Cash, Mobile payment (MTN, Telecel, AirtelTigo), Bank transfer, Cheque. The network and transaction ID are kept in the reference. |

### Two different "paid" figures, and why they differ

| Figure | Question it answers |
| --- | --- |
| **Paid so far** (on bills) | How much of *this term's bills* has been paid, whenever it was paid. |
| **Fees received** (cash) | How much fee money *came in during this term*, for any bill, old or new. |

They are rarely equal: fees received includes old debts paid this term, and paid so far includes payments made
before or after the term. **Both are correct.** Over the school's whole history they are equal (check A6).

---

## 2. Where to find each figure

Every finance page has the same **Academic Period** filter (years that open into 1st, 2nd and 3rd term).
Tick a year to see the whole year, or tick single terms to drill down.

| Page | What it shows |
| --- | --- |
| **Finance dashboard** | Total invoiced, money received, expenses, balance; fees by class type; optional services; expenses by category; mode of payment (click a mode for everyone who paid that way) |
| **Fee breakdown** | Term-by-term table (invoiced, paid so far, still owed, fees received, other income, expenses, term balance); fee items (Tuition, P.T.A, Feeding, Bus …) and who paid each |
| **Ledger** | Every single entry (receipt, income, expense) with the balance after it, counted from zero at the start of the chosen terms. Export to CSV. |
| **Income & expenses** | Money in and out other than student fees (capital, donations, salaries, utilities …) |
| **Student payments → student** | One family's invoice: this term's bills, balance brought forward, receipts |
| **Report card** | Next term fees = next term's school fees from Fee setup + services for that term + balance owed |

---

## 3. The checks

Tick each box. **Any line that does not match is a finding to investigate.**

### A. Every term (run at the end of each term)

Open **Fee breakdown**, tick one term.

| # | Check | How |
| --- | --- | --- |
| A1 | Invoiced = Paid so far + Still owed | Term-by-term table, one row |
| A2 | Term balance = Fees received + Other income − Expenses | Same row |
| A3 | Fee items add up to the term | "Billed · Paid · Owed" line above the fee item cards = Invoiced / Paid so far / Still owed in the table |
| A4 | Finance dashboard agrees | Same term on the dashboard: Total invoiced, Money received (= fees received + other income), Expenses, Term balance |
| A5 | Mode of payment adds up | Dashboard → Mode of payment total = Fees received for the term |
| A6 | Ledger agrees | Ledger for the term: money in = money received, money out = expenses, last "Term balance after" = term balance |
| A7 | Income & expenses agrees | Same term: the three tiles match the dashboard |

### B. Every school year

Tick the whole year.

| # | Check | How |
| --- | --- | --- |
| B1 | The year is the sum of its three terms | "Total" row in the term table = the year figures on the dashboard |
| B2 | Fees card = Total invoiced | Dashboard: "of … billed" in the Fees ring = Total invoiced card |
| B3 | Class types add up | Dashboard → Class type table: totals = Fees card |
| B4 | Services add up | Optional services card: Feeding + Bus + Extra-curricular + Sales = optional part of Total invoiced |

### C. All time (once a year, or when the auditor visits)

Tick every year.

| # | Check | How |
| --- | --- | --- |
| C1 | Balance = cash the school should hold now | All-time balance = cash in hand + bank balances on the day. **Count the cash and get bank statements: the system cannot do this part.** |
| C2 | Paid so far = Fees received | Over all time every payment has been counted in both |
| C3 | Mobile payment total = MoMo statements | Mode of payment → Mobile payment → compare with the MTN / Telecel / AirtelTigo merchant statements (reference holds the network and transaction ID) |
| C4 | Bank transfer + Cheque = bank statement credits | Same, against the bank statement |
| C5 | Expenses have receipts | Ledger → Money out: every expense has a reference and a supporting document |

### D. Student level (spot-check 5–10 families each term)

| # | Check | How |
| --- | --- | --- |
| D1 | The invoice adds up | Student payments → student: this term + balance brought forward = total due |
| D2 | Receipts match the parent's copies | Each receipt number (SF-…, OF-…) matches what the parent holds |
| D3 | Discounts are approved | A discount on the invoice matches an approved discount (Finance configuration → Discounts) |
| D4 | Report card is right | "Next term fees" = next term's fees + services + balance owed, as on the invoice |

---

## 4. Worked example: 3rd Term 2025 – 2026

| Figure | Amount | Check |
| --- | ---: | --- |
| Invoiced | 884,535 | |
| Paid so far | 806,183 | |
| Still owed | 78,352 | A1: 806,183 + 78,352 = 884,535 ✓ |
| Fees received | 718,134 | |
| Other income | 0 | |
| Expenses | 365,340 | |
| Term balance | 352,794 | A2: 718,134 + 0 − 365,340 = 352,794 ✓ |

Why fees received (718,134) is less than paid so far (806,183): 137,593 of this term's bills (mostly feeding and
bus) was paid in an earlier term, when parents paid for the whole year; and 49,544 of the money received was old
debt from earlier terms. 806,183 − 137,593 + 49,544 = 718,134 ✓.

## 5. Worked example: 2026 – 2027 Academic Year (so far)

| Figure | Amount |
| --- | ---: |
| Total invoiced | 1,638,730 |
| Paid so far / still owed | 994,095 / 644,635 |
| Money received | 1,194,285 (fees 994,285 + capital injection 200,000) |
| Expenses | 327,240 |
| Year balance | 867,045 |

Mode of payment: Cash 405,660 + Mobile payment 448,630 + Bank transfer 122,955 + Cheque 17,040 = **994,285** = fees received ✓.
Fees received (994,285) is 190 more than paid so far (994,095): 190 was old debt from 2025 – 2026 paid this year.

Terms 2 and 3 show as **Upcoming**: feeding, bus and clubs are already billed for them (a third each), no money has
moved yet.

---

## 6. Questions an auditor will ask

| Question | Answer / where |
| --- | --- |
| Why doesn't the term start with last term's cash? | Each term is reported on its own, from zero. The all-time view (check C1) gives the cash the school should hold. |
| Why is "paid" different from "received"? | Section 1. Paid is against the term's bills; received is cash in the term. |
| Where does the next term fee on the report card come from? | Fee setup (that term's fees for the class, less the student's discount) + services + balance owed. Nothing is typed in by hand. |
| Who can delete entries? | Only an administrator can delete income and expense entries. Accountants can record and edit. |
| How is a MoMo payment proven? | The receipt reference holds "MTN MoMo" and the transaction ID; match it to the MoMo merchant statement. |

---

## 7. Controls and the audit trail

| Control | How it works |
| --- | --- |
| **Reversing payments** | Only an administrator can reverse the payments on a bill (Student payments → bill → ⋯ → Reverse payments), and must give a reason. Accountants cannot. |
| **Reversed receipts are never lost** | Each reversed receipt is written to **Finance → Audit trail**: receipt number, bill, amount, mode, reference, date paid, who reversed it, when and why. The money leaves the totals; the record stays. |
| **Income / expense changes** | Every edit (old → new values) and every deletion (admin only) is written to the Audit trail. |
| **Receipt numbers** | A gap in SF- / OF- receipt numbers must match a reversed receipt in the Audit trail. |

**Audit check E1:** every reversal in the Audit trail has a sensible reason and was done by an administrator.
**Audit check E2:** every gap in receipt numbers appears in the Audit trail.

### Still outside the system

**Cash and bank are not reconciled in the system.** The system knows what *should* be held (C1); it does not record
actual bank balances or cash counts. Count the cash and compare with bank statements each month.

---

## 8. Routine

| When | Who | What |
| --- | --- | --- |
| Daily | Accountant | Record every payment with its mode and reference the same day; record expenses with receipts |
| Weekly | Accountant | Mobile payment and bank totals for the week vs MoMo / bank statements (C3, C4) |
| End of term | Accountant + owner | Checks A1–A7 and D1–D4; send invoices; print report cards |
| End of year | Owner / auditor | Checks B1–B4 and C1–C5; export the Ledger to CSV and keep it |
