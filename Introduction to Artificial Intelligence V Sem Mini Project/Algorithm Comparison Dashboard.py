import random

import matplotlib.pyplot as plt
import pandas as pd
import streamlit as st
from matplotlib.colors import ListedColormap

from Project import ALGORITHMS, GRID, SRC, DEST, compare

st.set_page_config(page_title="Algorithm Comparison Dashboard", layout="wide")
st.title("Algorithm Comparison Dashboard")
st.caption("Compare BFS, DFS, IDDFS, Best-First and A* on the same grid.")


def random_grid(rows, cols, blocked_pct, seed):
    rng = random.Random(seed)
    grid = [[0 if rng.random() < blocked_pct / 100 else 1 for _ in range(cols)]
            for _ in range(rows)]
    grid[0][0] = 1
    grid[rows - 1][cols - 1] = 1
    return grid


def draw_grid(grid, path, src, dest, title):
    fig, ax = plt.subplots(figsize=(4.5, 4.5))
    # 0 = blocked (dark), 1 = open (white), 2 = path (light green)
    show = [row[:] for row in grid]
    for r, c in (path or []):
        show[r][c] = 2
    ax.imshow(show, cmap=ListedColormap(["#333333", "#ffffff", "#8fd694"]), vmin=0, vmax=2)
    ax.text(src[1], src[0], "S", ha="center", va="center", color="red", fontweight="bold")
    ax.text(dest[1], dest[0], "D", ha="center", va="center", color="blue", fontweight="bold")
    ax.set_xticks([])
    ax.set_yticks([])
    ax.set_title(title)
    return fig


# ---------------- Sidebar ----------------
st.sidebar.header("Settings")
mode = st.sidebar.radio("Grid", ["Assignment grid (9x10)", "Random grid"])

if mode == "Random grid":
    rows = st.sidebar.slider("Rows", 5, 30, 12)
    cols = st.sidebar.slider("Columns", 5, 30, 12)
    blocked = st.sidebar.slider("Blocked cells (%)", 0, 40, 20)
    seed = st.sidebar.number_input("Seed", 0, 9999, 1)
    grid = random_grid(rows, cols, blocked, seed)
    src, dest = (0, 0), (rows - 1, cols - 1)
else:
    grid, src, dest = GRID, SRC, DEST

chosen = st.sidebar.multiselect("Algorithms", list(ALGORITHMS), default=list(ALGORITHMS))
run = st.sidebar.button("Run comparison", type="primary")

# ---------------- Main ----------------
if not run:
    st.pyplot(draw_grid(grid, None, src, dest, "Grid (S = start, D = destination)"))
    st.info("Pick settings in the sidebar and click **Run comparison**.")
else:
    results = [r for r in compare(grid, src, dest) if r["Algorithm"] in chosen]
    if not results:
        st.warning("Select at least one algorithm.")
        st.stop()

    df = pd.DataFrame(results).drop(columns=["Path"]).set_index("Algorithm")
    st.subheader("Results")
    st.dataframe(df)

    if df["Path Length"].isna().all():
        st.error("No path exists on this grid. Try another seed or fewer blocked cells.")

    c1, c2, c3 = st.columns(3)
    c1.markdown("**Nodes visited**")
    c1.bar_chart(df["Nodes Visited"])
    c2.markdown("**Path length**")
    c2.bar_chart(df["Path Length"])
    c3.markdown("**Time (ms)**")
    c3.bar_chart(df["Time (ms)"])

    st.subheader("Path found")
    cols_ui = st.columns(len(results))
    for col, r in zip(cols_ui, results):
        col.pyplot(draw_grid(grid, r["Path"], src, dest, r["Algorithm"]))

#To Run C:\Python315\python.exe -m streamlit run Algorithm_Comparison_Dashbaord.py
