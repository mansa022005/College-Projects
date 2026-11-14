## Algorithm Comparison Dashboard

A Streamlit application for visualizing and comparing classic search algorithms on a two-dimensional grid. Choose the supplied 9 × 10 assignment grid or create a seeded random grid, select algorithms, and compare their search statistics and paths.

## Features

- Compare Breadth-First Search (BFS), Depth-First Search (DFS), Iterative Deepening DFS (IDDFS), Best-First Search, and A*.
- Use the assignment grid or generate a random grid with 5–30 rows, 5–30 columns, and 0–40% blocked cells.
- Set a random seed to reproduce a generated grid. The start and destination cells are kept open.
- Select which algorithms to run.
- View a results table and charts for nodes visited, path length, and elapsed time.
- View each algorithm's path on the grid. Blocked cells, open cells, and path cells are drawn with different colors.

## Requirements

- Python
- Streamlit
- Matplotlib
- Pandas

The dashboard also imports Python's standard `random` module.

## Important: required algorithm module

The dashboard imports `ALGORITHMS`, `GRID`, `SRC`, `DEST`, and `compare` from `Project.py`. That module is not included in this repository folder at the linked revision, so the dashboard will not run until `Project.py` is added to the same directory. It must define the algorithm registry, the assignment grid and its start and destination coordinates, and the comparison function that returns result records with the fields used by the dashboard, including `Algorithm`, `Path`, `Path Length`, `Nodes Visited`, and `Time (ms)`.

## Installation and run

1. Open a terminal in this project folder.
2. Install the Python packages:

   ```bash
   py -m pip install streamlit matplotlib pandas
   ```

3. Add the required `Project.py` module described above.
4. Start the application:

   ```bash
   py -m streamlit run "Algorithm Comparison Dashboard.py"
   ```

5. Open the local URL printed by Streamlit in your browser.

## How to use

1. Choose **Assignment grid (9x10)** or **Random grid** in the sidebar.
2. For a random grid, set its dimensions, blocked-cell percentage, and seed.
3. Select one or more algorithms.
4. Click **Run comparison** to view the metrics, charts, and resulting paths.

If no algorithm is selected, the dashboard asks you to select at least one. If none of the selected algorithms finds a path, it reports that no path exists for the current grid and suggests trying another seed or reducing the blocked-cell percentage.

## Project files

```text
Introduction to Artificial Intelligence V Sem Mini Project/
├── Algorithm Comparison Dashboard.py  # Streamlit interface and grid visualizations
├── Algorithms.py                         # Required algorithm module; absent at this revision
├── AI Mini Project Report.pdf         # Project report
└── README.md
```

The project report is available as [AI Mini Project Report.pdf](AI%20Mini%20Project%20Report.pdf).

## Interpreting results

The reported values describe the behavior of the algorithms and their implementations on the selected grid. Elapsed time can vary by machine and run. The dashboard source does not include the search implementation, so details such as neighbor ordering, heuristic choice, tie-breaking, and repeated-state handling depend on the missing `Project.py` module. Treat any optimality claims as dependent on those implementation details and the grid's movement and cost rules.

