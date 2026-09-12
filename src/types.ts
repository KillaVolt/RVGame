export type HudState = "UNKNOWN" | "LOOKS_OK" | "WATCH" | "FAULT" | "TOWING_BLOCKED";

export interface MoneyState {
  cashCents: number;
  loanPrincipalCents: number;
  accruedInterestCents: number;
  payablesCents: number;
  totalDebtCents: number;
}

export interface HudSystem {
  id: string;
  label: string;
  state: HudState;
  evidence: string;
}

export interface CamperView {
  instanceId: string;
  archetypeId: string;
  name: string;
  locationId: string;
  locationName: string;
  dream: boolean;
  towedKm: number;
  transportedKm: number;
  hud: HudSystem[];
  knownDefects: Array<{ id: string; systemId: string; severity: string; resolved: boolean }>;
  upgrades: string[];
  projectCostCents: number;
}

export interface RouteView {
  id: string;
  destinationId: string;
  destinationName: string;
  distanceKm: number;
  towCostCents: number;
  soloCostCents: number;
  canTow: boolean;
  canSolo: boolean;
  towReason: string | null;
  towHours: number;
  soloHours: number;
}

export interface MapRouteView {
  id: string;
  a: string;
  b: string;
  distanceKm: number;
  reachable: boolean;
}

export interface StartOption {
  id: string;
  name: string;
  tagline: string;
  description: string;
  equipment: string[];
  benefit: string;
}

export interface ListingView {
  id: string;
  name: string;
  archetypeId: string;
  locationId: string;
  locationName: string;
  askCents: number;
  dream: boolean;
  knownIssues: string[];
  canBuy: boolean;
  buyReason: string | null;
}

export interface OfferView {
  id: string;
  buyerName: string;
  amountCents: number;
  projectProfitCents: number;
}

export interface RepairView {
  id: string;
  label: string;
  systemId: string;
  technicianCostCents: number;
  technicianHours: number;
  technicianAvailable: boolean;
  technicianReason: string | null;
  diyAllowed: boolean;
  diyHours: number | null;
  diyAvailable: boolean;
  diyReason: string | null;
}

export interface UpgradeView {
  id: string;
  label: string;
  costCents: number;
  hours: number;
  available: boolean;
  reason: string | null;
}

export interface GameState {
  buildId: string;
  contentVersion: string;
  campaignId: string;
  revision: number;
  status: "active" | "completed";
  day: number;
  hour: number;
  player: { locationId: string; locationName: string; distanceUnit: "km" | "mi"; approachId: string; approachName: string; tools: string[]; perks: string[] };
  inspection: { selfHours: number; technicianCostCents: number; technicianHours: number; selfAvailable: boolean; selfReason: string | null };
  finances: MoneyState;
  towVehicle: { name: string; locationId: string; odometerKm: number };
  objective: string;
  camper: CamperView | null;
  routes: RouteView[];
  listings: ListingView[];
  offers: OfferView[];
  repairs: RepairView[];
  upgrades: UpgradeView[];
  mapLocations: Array<{ id: string; name: string; x: number; y: number; current: boolean; camper: boolean }>;
  mapRoutes: MapRouteView[];
  recoveryPayableCents: number;
  recoveryHours: number;
  work: { grossCents: number; hours: number };
  market: { waitHours: number; saleHours: number };
  atGarageWithCamper: boolean;
  canAdvertise: boolean;
  canWait: boolean;
  canRecover: boolean;
  canComplete: boolean;
  saleCount: number;
  eventLog: Array<{ day: number; hour: number; text: string; tone: string }>;
}

export interface ApiResponse {
  ok: boolean;
  state?: GameState | null;
  setup?: StartOption[];
  community?: CommunitySummary;
  result?: { summary: string };
  duplicate?: boolean;
  error?: { code: string; message: string };
}

export interface CommunitySummary {
  averageRating: number | null;
  ratingCount: number;
  userRating: number | null;
}

export interface Bootstrap {
  state: GameState | null;
  setup: StartOption[];
}
