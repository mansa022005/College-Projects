echo "=====FCFS CPU SCHEDULING====="

#Input number of processes
echo "Enter the number of processes: "
read n

#Input Burst Time
for ((i=0; i<n; i++))
do
    echo "Enter the burst time for Process P$i : "
    read bt[i]
done

#First process has zero waiting time
wt[0]=0

#Calculate waiting time for each process
for ((i=1; i<n; i++))
do
    wt[i]=$((wt[i-1] + bt[i-1]))
done

#Calculate turnaround time for each process
for ((i=0; i<n; i++))
do
    tat[i]=$((wt[i] + bt[i]))
done

#Displaying Results
echo ""
echo "_____________________________________________________"
echo "Process | Burst Time | Waiting Time | Turnaround Time"
echo "_____________________________________________________"

total_wt=0
total_tat=0

for ((i=0; i<n; i++))
do
    echo "P$i      | ${bt[i]}         | ${wt[i]}           | ${tat[i]}"
    total_wt=$((total_wt + wt[i]))
    total_tat=$((total_tat + tat[i]))
done

#Averages
avg_wt=$(echo "scale=2; $total_wt / $n" | bc)
avg_tat=$(echo "scale=2; $total_tat / $n" | bc)

echo "_____________________________________________________"
echo "Average Waiting Time: $avg_wt"
echo "Average Turnaround Time: $avg_tat"
