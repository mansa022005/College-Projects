import heapq
import time
from collections import deque

DIRS = [(-1, 0), (1, 0), (0, -1), (0, 1)]

GRID = [
    [1, 0, 1, 1, 1, 1, 0, 1, 1, 1],
    [1, 1, 1, 0, 1, 1, 1, 0, 1, 1],
    [1, 1, 1, 0, 1, 1, 0, 1, 0, 1],
    [0, 0, 1, 0, 1, 0, 0, 0, 0, 1],
    [1, 1, 1, 0, 1, 1, 1, 0, 1, 0],
    [1, 0, 1, 1, 1, 1, 0, 1, 0, 0],
    [1, 0, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 1, 1, 1, 1, 0, 1, 1, 1],
    [1, 1, 1, 0, 0, 0, 1, 0, 0, 1],
]
SRC, DEST = (8, 0), (0, 0)


def neighbors(grid, node):
    r, c = node
    for dr, dc in DIRS:
        nr, nc = r + dr, c + dc
        if 0 <= nr < len(grid) and 0 <= nc < len(grid[0]) and grid[nr][nc] == 1:
            yield (nr, nc)


def build_path(parent, dest):
    path, node = [], dest
    while node is not None:
        path.append(node)
        node = parent[node]
    return path[::-1]


def heuristic(a, b):
    """Manhattan distance (suits 4-direction movement)."""
    return abs(a[0] - b[0]) + abs(a[1] - b[1])


# ---------------- BFS ----------------
def bfs(grid, src, dest):
    queue = deque([src])
    parent = {src: None}
    visited = 0
    while queue:
        node = queue.popleft()
        visited += 1
        if node == dest:
            return build_path(parent, dest), visited
        for nb in neighbors(grid, node):
            if nb not in parent:
                parent[nb] = node
                queue.append(nb)
    return None, visited


# ---------------- DFS ----------------
def dfs(grid, src, dest):
    stack = [src]
    parent = {src: None}
    visited = 0
    while stack:
        node = stack.pop()
        visited += 1
        if node == dest:
            return build_path(parent, dest), visited
        for nb in neighbors(grid, node):
            if nb not in parent:
                parent[nb] = node
                stack.append(nb)
    return None, visited


# ---------------- IDDFS ----------------
def iddfs(grid, src, dest, max_depth=100):
    visited = 0

    def limited(node, limit, path, best_depth):
        nonlocal visited
        visited += 1
        if node == dest:
            return True
        if limit == 0:
            return False
        for nb in neighbors(grid, node):
            depth = len(path)
            # skip if already reached this node at same/shallower depth
            if best_depth.get(nb, float("inf")) <= depth:
                continue
            best_depth[nb] = depth
            path.append(nb)
            if limited(nb, limit - 1, path, best_depth):
                return True
            path.pop()
        return False

    for depth in range(max_depth + 1):
        path = [src]
        if limited(src, depth, path, {src: 0}):
            return path, visited
    return None, visited


# ---------------- Greedy Best-First ----------------
def best_first(grid, src, dest):
    heap = [(heuristic(src, dest), src)]
    parent = {src: None}
    closed = set()
    visited = 0
    while heap:
        _, node = heapq.heappop(heap)
        if node in closed:
            continue
        closed.add(node)
        visited += 1
        if node == dest:
            return build_path(parent, dest), visited
        for nb in neighbors(grid, node):
            if nb not in parent:
                parent[nb] = node
                heapq.heappush(heap, (heuristic(nb, dest), nb))
    return None, visited


# ---------------- A* ----------------
def a_star(grid, src, dest):
    heap = [(heuristic(src, dest), 0, src)]
    parent = {src: None}
    g_cost = {src: 0}
    closed = set()
    visited = 0
    while heap:
        _, g, node = heapq.heappop(heap)
        if node in closed:
            continue
        closed.add(node)
        visited += 1
        if node == dest:
            return build_path(parent, dest), visited
        for nb in neighbors(grid, node):
            new_g = g + 1
            if new_g < g_cost.get(nb, float("inf")):
                g_cost[nb] = new_g
                parent[nb] = node
                heapq.heappush(heap, (new_g + heuristic(nb, dest), new_g, nb))
    return None, visited


ALGORITHMS = {
    "BFS": bfs,
    "DFS": dfs,
    "IDDFS": iddfs,
    "Best-First": best_first,
    "A*": a_star,
}


def compare(grid=GRID, src=SRC, dest=DEST):
    """Run every algorithm and return a list of result dicts."""
    results = []
    for name, algo in ALGORITHMS.items():
        start = time.perf_counter()
        path, visited = algo(grid, src, dest)
        elapsed_ms = (time.perf_counter() - start) * 1000
        results.append({
            "Algorithm": name,
            "Nodes Visited": visited,
            "Path Length": len(path) - 1 if path else None,
            "Time (ms)": round(elapsed_ms, 3),
            "Path": path,
        })
    return results


if __name__ == "__main__":
    results = compare()
    print(f"{'Algorithm':<12}{'Nodes Visited':<15}{'Path Length':<13}{'Time (ms)':<10}")
    print("-" * 50)
    for r in results:
        print(f"{r['Algorithm']:<12}{r['Nodes Visited']:<15}{str(r['Path Length']):<13}{r['Time (ms)']:<10}")
