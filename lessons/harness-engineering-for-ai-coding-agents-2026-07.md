# Harness engineering for AI coding agents

## WHAT YOU ALREADY KNOW

From the store, three claims already frame this topic:

1. Harness engineering holds the model and coding agent constant and improves the external levers of context and tools. (high, fact; briefed from https://github.com/lopopolo/harness-engineering)
2. Accepted work, corrections, and failures should become durable harness artifacts so coherence accumulates across agent-maintained systems. (medium, inference; same brief)
3. A thin durable store plus an external agent runtime can mirror the OpenWorker split: PHP owns state, the coding agent owns research and synthesis. (low, synthesis; from the OpenWorker brief). Treat this third point as a working hypothesis, not settled fact.

Related store context: OpenWorker keeps the agent loop and secrets local, and gates consequential actions behind approval. That is adjacent harness thinking even when the word harness never appears.

## CURRENT STATE

Fresh research on 2026-07-25:

- lopopolo/harness-engineering (created 2026-07-18) presents harness engineering as last-mile environment design for agents. Primary source: the repository README and linked docs on making nonfunctional requirements recoverable. (source_date 2026-07-25)
- andrewyng/openworker (created 2026-07-20) ships a local desktop agent with connectors, MCP hooks, and approval gates. Primary source: OpenWorker README. (source_date 2026-07-25)
- simonw/llm remains a mature CLI pattern for multi-provider prompts with optional SQLite logs. Primary source: https://github.com/simonw/llm and llm.datasette.io. (source_date 2026-07-25)

Star totals on recently created repos are current snapshots, not weekly growth rates.

## THE CORE IDEA

Synthesis: an AI coding agent gets better less by swapping models and more by giving it a recoverability harness. The harness is the combination of durable claims, lesson files, approval rules, tools, and examples that carry your real quality bar into the next run.

Store evidence supplies the local preference for PHP-SQLite durability. Live sources supply the vocabulary and proof that teams are packaging this as explicit practice in 2026.

## HOW IT PLAYS OUT

### 1. Hold the worker constant, move the environment

Concept: treat the model and agent product as a black box for a stretch of work.
Why it matters: if you change model, tools, and prompts at once, you cannot tell what helped.
Example: point Cursor at a repo that already has AGENTS.md, schema.sql, and a claims table instead of restating house rules in every chat.
Store connection: the harness-engineering high-confidence fact about external levers.
You should now be able to: decide which lever you are changing before a run begins.

### 2. Make private process data recoverable

Concept: general weights do not contain your authority rules, exception history, or current operational state.
Why it matters: agents invent plausible process when the real process is invisible.
Example: store claims with source_url, confidence, and claim_type so a later agent can see what is fact versus synthesis.
Store connection: OpenWorker local-first secrets and Learn System provenance fields.
You should now be able to: refuse to store a claim that lacks a source.

### 3. Dual-write readable lessons and queryable claims

Concept: long-form teaching lives in markdown; retrieval lives in SQL rows linked by lesson_file.
Why it matters: lessons evaporate if the only residue is chat history.
Example: after a topic run, write lessons/topic-slug-YYYY-MM.md and extract 3 to 6 transferable claims.
Store connection: llm's SQLite logging pattern and this system's dual write-back.
You should now be able to: explain why a claim without lesson_file or item_id is harder to audit.

### 4. Gate consequential actions

Concept: reads can be broad; writes need approval or structured validation.
Why it matters: unattended agents create confident damage.
Example: PHP validate.php rejects save-brief payloads missing provenance before SQLite is touched.
Store connection: OpenWorker approval gates before sends and shell commands.
You should now be able to: name one write path in your stack that must validate before mutate.

### 5. Leave the next run better equipped

Concept: every accepted correction should become context, a check, or a claim.
Why it matters: without write-back, each session starts from celebrity-model defaults.
Example: STUDY NEXT topics enter the queue with added_by=gap-suggestion and a source_note pointing at the lesson that found the gap.
Store connection: cumulative coherence inference from the harness brief.
You should now be able to: turn one gap into a queued topic instead of a forgotten aside.

## CONTESTED / MOVING

- Whether star velocity on brand-new agent repos predicts durable practice or only launch curiosity. Current star totals are weak proxies either way.
- How much harness belongs in-repo (AGENTS.md, tests) versus in an external store (claims database). Sources agree the environment matters; they disagree on packaging.
- Local-first desktop agents versus cloud agent products. OpenWorker argues local control; many hosted tools argue convenience. Choose by data sensitivity, not fashion.

## GAPS THIS FILLED

- Named harness engineering as a practice with primary-source vocabulary.
- Connected that practice to an existing PHP-SQLite claim store and lesson files.
- Clarified that low-confidence synthesis about architecture mirroring must stay labeled synthesis.

## STUDY NEXT

- AGENTS.md patterns that route tasks to proof and constraints
- MCP connector gateways for agent tool access (OpenConnector)
- SQLite as an agent log and claim index for indie PHP apps

## NEW CLAIMS SAVED

Claims written back in this session are listed in the save-lesson payload and linked through lesson_file.

## CHECK YOUR UNDERSTANDING

1. Why does holding the model constant help you evaluate a harness change?
<details class="answer"><summary>Answer</summary>
Because if the model, tools, and prompts all change together, you cannot attribute better or worse output to the environment change you care about.
</details>

2. What must every stored claim carry before it is allowed into the database?
<details class="answer"><summary>Answer</summary>
A source_url, plus confidence and claim_type. Provenance is required; unsupported statements do not enter the store.
</details>

3. How should you treat a low-confidence synthesis that says your PHP store mirrors OpenWorker?
<details class="answer"><summary>Answer</summary>
Keep it labeled synthesis and low confidence. Use it as a design prompt, never as settled fact in high-stakes planning.
</details>

4. What is the difference between a lesson file and a claim row?
<details class="answer"><summary>Answer</summary>
The lesson file is durable long-form teaching. The claim row is a queryable, tagged sentence with provenance that agents can retrieve quickly.
</details>
