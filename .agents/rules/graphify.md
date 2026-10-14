---
trigger: always_on
description: Consult the graphify knowledge graph at graphify-out/ for codebase and architecture questions.
---

## graphify

This project has a graphify knowledge graph at graphify-out/.

Rules:

- The primary in-app language is English, Indonesian (Bahasa) is the secondary language. The codebase and documentation language is English
- Adheres to CruddyByDesign, KISS, and DRY principles
- Avoid technical debts!
- Follows Test Driven Development, create test for every methods created
- Checks for existing components instead of creating new one and componentize codes that appears in multiple files (especially for views/front-end)
- Follow Security best practice
- New: If available, use shadcn-vue components for uniformity, consistency, and simplicity
- New: Follow existing margins and paddings of similar component to ensure uniformity and consistency
- New: Aggresively optimize for better performance to ensure a smooth User Experience
- New: Componentize view files in a logical way that make sense to avoid long files (max 500-ish line of codes) and group the components tidyly and consistently to ensure easy maintenance
- New: The app has not been used in production yet. Maximize uniformity, consistency, and clean code without backward-compatibility shims, aliases, or accessors/mutators. Refactor cleanly across the board instead of preserving legacy compatibility aliases.
- For codebase or architecture questions, when `graphify-out/graph.json` exists, first run `graphify query "<question>"` (CLI) or `query_graph` (MCP). Use `graphify path "<A>" "<B>"` / `shortest_path` for relationships and `graphify explain "<concept>"` / `get_node` for focused concepts. These return a scoped subgraph, usually much smaller than `GRAPH_REPORT.md` or raw grep output.
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)
