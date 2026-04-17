# 📌 FCFS CPU Scheduling (Shell Script)

---

👨‍💻 Team Members
Manvendra Singh Chouhan - 243501117

Lakshay Gangwani - 2435011104

---

# 🧠 Overview

This project implements the First Come First Serve (FCFS) CPU Scheduling Algorithm using a Bash Shell Script.

FCFS is one of the simplest scheduling algorithms in Operating Systems where processes are executed in the order they arrive.

---

# ⚙️ Features
Takes input for number of processes
Accepts burst time for each process
Calculates:
Waiting Time (WT)
Turnaround Time (TAT)
Displays results in tabular format
Computes average waiting time and turnaround time

---

# 🧮 Algorithm Used
🔹 FCFS Scheduling
Non-preemptive algorithm
Processes are executed in the order of arrival
Formula used:
Waiting Time (WT) = Previous WT + Previous BT
Turnaround Time (TAT) = WT + BT

---

# 📦 Requirements
Linux / Unix environment
Bash Shell
bc calculator (for decimal calculations)

---

# ⚠️ Limitations
Does not consider arrival time (assumes all processes arrive at same time)
Not efficient for long processes (Convoy Effect)

---

# 🚀 Future Improvements
Add arrival time support
Implement other scheduling algorithms:
SJF (Shortest Job First)
Round Robin
Add Gantt Chart visualization

---
