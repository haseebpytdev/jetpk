/**
 * CQ43-R2 widget identity: server message_id dedup (not body).
 * Run: node tests/ask-jetpakistan-chat-continuity.test.mjs
 *
 * Behavioral merge mirrors frontend/features/ai-assistant/lib/assistantMessageIdentity.ts
 */
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const read = (rel) => readFileSync(join(root, rel), "utf8");

let fail = 0;
const gates = {};

function check(label, ok) {
  console.log(ok ? "PASS" : "FAIL", label);
  gates[label] = ok ? "PASS" : "FAIL";
  if (!ok) fail += 1;
}

function appendAssistantById(previous, incoming) {
  if (previous.some((message) => message.id === incoming.id)) {
    return previous;
  }
  return [
    ...previous,
    {
      id: incoming.id,
      role: "assistant",
      body: incoming.body,
      recommendations: incoming.recommendations,
      actions: incoming.actions,
    },
  ];
}

function mergePolledAssistantsById(previous, incoming, hooks = {}) {
  const known = new Set(previous.map((message) => message.id));
  const next = [...previous];
  for (const message of incoming) {
    const numericId = typeof message.id === "number" ? message.id : Number(message.id);
    if (Number.isFinite(numericId) && typeof hooks.advanceLastPollId === "function") {
      hooks.advanceLastPollId(numericId);
    }
    const id = String(message.id);
    if (known.has(id) || message.role === "user") continue;
    known.add(id);
    next.push({
      id,
      role: message.role || "assistant",
      body: message.body,
      recommendations: message.meta?.recommendations,
      actions: message.meta?.actions,
    });
  }
  return next;
}

const chatSrc = read("features/ai-assistant/components/AskJetPakistanChat.tsx");
const identitySrc = read("features/ai-assistant/lib/assistantMessageIdentity.ts");

check("BODY_DEDUP_REMOVED", !chatSrc.includes("duplicateBody") && !identitySrc.includes("duplicateBody"));
check(
  "SERVER_ID_IDENTITY",
  chatSrc.includes("appendAssistantById") &&
    chatSrc.includes("mergePolledAssistantsById") &&
    identitySrc.includes("appendAssistantById") &&
    identitySrc.includes("previous.some((message) => message.id === incoming.id)"),
);

// A — distinct IDs, same body → both render
{
  let messages = [];
  messages = appendAssistantById(messages, {
    id: "103",
    body: "Please share your travel date.",
  });
  messages = appendAssistantById(messages, {
    id: "104",
    body: "Please share your travel date.",
  });
  check(
    "SAME_BODY_DISTINCT_IDS_RENDERED",
    messages.length === 2 && messages[0].id === "103" && messages[1].id === "104",
  );
}

// B — CASE A POST wins then poll same id → once
{
  let messages = [];
  let lastPollId = 0;
  messages = appendAssistantById(messages, { id: "101", body: "Same confirmation" });
  lastPollId = Math.max(lastPollId, 101);
  messages = mergePolledAssistantsById(
    messages,
    [{ id: 101, role: "assistant", body: "Same confirmation" }],
    { advanceLastPollId: (id) => { lastPollId = Math.max(lastPollId, id); } },
  );
  check(
    "POST_POLL_SAME_ID_DEDUP_POST_FIRST",
    messages.length === 1 && messages[0].id === "101" && lastPollId === 101,
  );
}

// B — CASE B poll wins then POST same id → once
{
  let messages = [];
  let lastPollId = 0;
  messages = mergePolledAssistantsById(
    messages,
    [{ id: 102, role: "assistant", body: "Same confirmation" }],
    { advanceLastPollId: (id) => { lastPollId = Math.max(lastPollId, id); } },
  );
  messages = appendAssistantById(messages, { id: "102", body: "Same confirmation" });
  check(
    "POST_POLL_SAME_ID_DEDUP_POLL_FIRST",
    messages.length === 1 && messages[0].id === "102" && lastPollId === 102,
  );
}

check(
  "POST_POLL_SAME_ID_DEDUP",
  gates.POST_POLL_SAME_ID_DEDUP_POST_FIRST === "PASS" &&
    gates.POST_POLL_SAME_ID_DEDUP_POLL_FIRST === "PASS",
);

// C — different IDs / different bodies
{
  let messages = [];
  messages = appendAssistantById(messages, { id: "1", body: "Hello" });
  messages = appendAssistantById(messages, { id: "2", body: "World" });
  check(
    "DISTINCT_IDS_DISTINCT_BODIES",
    messages.length === 2 && messages[0].body === "Hello" && messages[1].body === "World",
  );
}

// D/E — busy clear is finally{} in send (source contract)
check(
  "THINKING_BUSY_CLEARS",
  /finally\s*\{\s*endBusy\(\);/s.test(chatSrc) && chatSrc.includes("beginBusy"),
);

// F — conversation_id update preserved
check(
  "CONVERSATION_ID_UNCHANGED_CONTRACT",
  chatSrc.includes("json.conversation_id") && chatSrc.includes("setConversationId"),
);

// G — polling still wired
check(
  "MESSAGE_POLLING_WORKS",
  chatSrc.includes("/api/public/ai/messages") &&
    chatSrc.includes("since_id=") &&
    chatSrc.includes("mergePolledAssistantsById"),
);

check(
  "CLIENT_MESSAGE_IDENTITY",
  gates.BODY_DEDUP_REMOVED === "PASS" &&
    gates.SERVER_ID_IDENTITY === "PASS" &&
    gates.SAME_BODY_DISTINCT_IDS_RENDERED === "PASS" &&
    gates.POST_POLL_SAME_ID_DEDUP === "PASS",
);

console.log(JSON.stringify({ fail, gates }, null, 2));
process.exit(fail ? 1 : 0);
