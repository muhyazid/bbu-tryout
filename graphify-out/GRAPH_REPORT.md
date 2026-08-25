# Graph Report - .  (2026-08-02)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 38 nodes · 15 edges · 28 communities (27 shown, 1 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 1 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8d362580`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- sesi_helper.php
- redirect
- helpers.php

## God Nodes (most connected - your core abstractions)
1. `redirect()` - 4 edges
2. `cari_sesi_aktif_peserta()` - 3 edges
3. `url()` - 2 edges
4. `normalisasi_jam()` - 2 edges
5. `validasi_sesi_peserta()` - 2 edges
6. `cekAdmin()` - 2 edges
7. `cekPeserta()` - 2 edges

## Surprising Connections (you probably didn't know these)
- `redirect()` --calls--> `url()`  [INFERRED]
  session.php → includes/helpers.php

## Import Cycles
- None detected.

## Communities (28 total, 1 thin omitted)

### Community 0 - "sesi_helper.php"
Cohesion: 0.60
Nodes (4): cari_sesi_aktif_peserta(), normalisasi_jam(), validasi_sesi_peserta(), mysqli

### Community 1 - "redirect"
Cohesion: 0.83
Nodes (3): cekAdmin(), cekPeserta(), redirect()

## Knowledge Gaps
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `redirect()` connect `redirect` to `helpers.php`?**
  _High betweenness centrality (0.014) - this node is a cross-community bridge._
- **Why does `url()` connect `helpers.php` to `redirect`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._