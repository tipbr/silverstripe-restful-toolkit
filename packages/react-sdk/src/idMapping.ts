import type { Identifier } from './types';

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const MAX_COLLISION_RETRIES = 20;

export interface IdMappingConfig {
  enabled?: boolean;
  shortIds?: boolean;
  shortIdLength?: number;
}

const fnv1a = (value: string): string => {
  let hash = 0x811c9dc5;
  for (let i = 0; i < value.length; i += 1) {
    hash ^= value.charCodeAt(i);
    hash = (hash >>> 0) * 0x01000193;
  }

  return (hash >>> 0).toString(36);
};

const isUuid = (value: string): boolean => UUID_REGEX.test(value);

const isIdKey = (key: string): boolean => {
  const normalized = key.toLowerCase();
  return normalized === 'id' || normalized.endsWith('_id');
};

export class IdMapper {
  private readonly enabled: boolean;
  private readonly shortIds: boolean;
  private readonly shortIdLength: number;
  private readonly shortToUuid = new Map<string, string>();
  private readonly uuidToShort = new Map<string, string>();

  constructor(config?: IdMappingConfig) {
    this.enabled = Boolean(config?.enabled);
    this.shortIds = Boolean(config?.shortIds);
    this.shortIdLength = Math.max(6, Math.min(16, config?.shortIdLength ?? 8));
  }

  toApiId(id: Identifier): Identifier {
    if (!this.enabled || typeof id !== 'string') {
      return id;
    }

    const value = id.trim();
    if (value === '' || !this.shortIds || isUuid(value)) {
      return value;
    }

    return this.shortToUuid.get(value) ?? value;
  }

  toClientId(id: Identifier): Identifier {
    if (!this.enabled || typeof id !== 'string') {
      return id;
    }

    const value = id.trim();
    if (value === '' || !isUuid(value)) {
      return value;
    }

    if (!this.shortIds) {
      return value;
    }

    const existing = this.uuidToShort.get(value);
    if (existing) {
      return existing;
    }

    const short = this.createShortId(value);
    this.uuidToShort.set(value, short);
    this.shortToUuid.set(short, value);

    return short;
  }

  mapValueForClient<T>(value: T): T {
    return this.mapInternal(value, null) as T;
  }

  private mapInternal(value: unknown, key: string | null): unknown {
    if (!this.enabled) {
      return value;
    }

    if (Array.isArray(value)) {
      return value.map((item) => this.mapInternal(item, null));
    }

    if (value && typeof value === 'object') {
      const mapped: Record<string, unknown> = {};
      Object.entries(value as Record<string, unknown>).forEach(([entryKey, entryValue]) => {
        mapped[entryKey] = this.mapInternal(entryValue, entryKey);
      });
      return mapped;
    }

    if (typeof value === 'string' && key && isIdKey(key)) {
      return this.toClientId(value);
    }

    return value;
  }

  private createShortId(uuid: string): string {
    let attempt = 0;

    while (attempt < MAX_COLLISION_RETRIES) {
      const seed = attempt === 0 ? uuid : `${uuid}:${attempt}`;
      const candidate = fnv1a(seed).padEnd(this.shortIdLength, '0').slice(0, this.shortIdLength);
      const existing = this.shortToUuid.get(candidate);
      if (!existing || existing === uuid) {
        return candidate;
      }

      attempt += 1;
    }

    return fnv1a(`${uuid}:fallback`).padEnd(this.shortIdLength, '0').slice(0, this.shortIdLength);
  }
}
