import type { AgentRecord, AgentsPageResult, AgentsQuery } from "@/types/agent";
import { buildAgentsPage } from "@/lib/agents-filter";
import { getAgentById, mockAgents } from "@/mocks/agent-fixtures";
import { useMockData } from "@/lib/preview";

export class AgentsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "AgentsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getAgentsPage(query: AgentsQuery): Promise<AgentsPageResult> {
  if (!useMockData()) {
    throw new AgentsServiceError("Live agent data is disabled in preview.", "AG-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new AgentsServiceError(
      "Mock agent service returned a recoverable error (preview simulation).",
      "AG-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildAgentsPage(query, mockAgents);
}

export async function getAgentDetail(id: string): Promise<AgentRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getAgentById(id) ?? null;
}

export function listAllMockAgents(): AgentRecord[] {
  return mockAgents;
}
