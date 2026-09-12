import type { ApiResponse, Bootstrap, GameState } from "./types";

const endpoint = import.meta.env.DEV ? "/api" : "../api/index.php";

async function send(body?: object): Promise<ApiResponse> {
  const response = await fetch(endpoint, {
    method: body ? "POST" : "GET",
    credentials: "same-origin",
    headers: body
      ? { "Content-Type": "application/json", "X-RVGame": "1" }
      : undefined,
    body: body ? JSON.stringify(body) : undefined
  });

  const data = (await response.json()) as ApiResponse;
  if (!response.ok || !data.ok) {
    throw new Error(data.error?.message || "The game server rejected the request.");
  }
  return data;
}

export async function loadGame(): Promise<Bootstrap> {
  const response = await send();
  return { state: response.state, setup: response.setup || [] };
}

export async function newGame(loadoutId: string): Promise<GameState> {
  const state = (await send({ action: "new", loadoutId })).state;
  if (!state) throw new Error("The server did not return a new campaign.");
  return state;
}

export async function resetGame(): Promise<void> {
  await send({ action: "reset" });
}

export async function command(
  state: GameState,
  type: string,
  payload: Record<string, unknown> = {}
): Promise<GameState> {
  const response = await send({
    action: "command",
    commandId: crypto.randomUUID(),
    expectedRevision: state.revision,
    type,
    payload
  });
  if (!response.state) throw new Error("The server did not return campaign state.");
  return response.state;
}
