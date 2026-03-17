const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080';

async function fetchApi<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const url = `${API_URL}${endpoint}`;
  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: response.statusText }));
    throw new Error(error.error || `API error: ${response.status}`);
  }

  return response.json();
}

export const api = {
  getCatalog: (page = 1, limit = 20) =>
    fetchApi<{ categories: Category[]; products: Product[]; total: number; page: number; limit: number }>(
      `/api/catalog?page=${page}&limit=${limit}`
    ),

  getCategories: () =>
    fetchApi<{ categories: Category[] }>('/api/catalog/categories'),

  getProduct: (slug: string) =>
    fetchApi<ProductDetail | { error: string }>(`/api/product/${slug}`),

  calculate: (config: CalculateRequest) =>
    fetchApi<CalculationResult>('/api/calculate', {
      method: 'POST',
      body: JSON.stringify({ config }),
    }),

  createEstimate: (config: ConfigurationInput, guestToken?: string) =>
    fetchApi<EstimateResult>('/api/estimate', {
      method: 'POST',
      body: JSON.stringify({ config, guestToken }),
    }),

  getEstimate: (id: number) =>
    fetchApi<EstimateDetail>(`/api/estimate/${id}`),

  createOrder: (estimateId: number, contact: Contact) =>
    fetchApi<{ orderId: number }>('/api/order', {
      method: 'POST',
      body: JSON.stringify({ estimateId, contact }),
    }),

  createLead: (data: LeadInput) =>
    fetchApi<{ id: number }>('/api/lead', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  chat: (message: string, sessionId?: string, context?: object) =>
    fetchApi<{ response: string }>('/api/chat', {
      method: 'POST',
      body: JSON.stringify({ message, sessionId, context }),
    }),
};

export type Category = { id: number; name: string; slug: string };
export type Product = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  category: Category;
  variants: { id: number; sku: string; attributes: Record<string, unknown> }[];
};
export type ProductDetail = Product;

export type ConfigurationInput = {
  productId: number;
  variantId: number;
  quantity: number;
  options?: Record<string, number>;
  extras?: string[];
};
export type CalculateRequest = ConfigurationInput;

export type CalculationResult = {
  total: number;
  currency: string;
  breakdown: { description: string; quantity: number; unitPrice: number; total: number }[];
  materials: { name: string; quantity: number; unit: string }[];
  services: { name: string; price: number }[];
};

export type EstimateResult = {
  estimateId: number;
  total: number;
  currency: string;
  lines: { description: string; quantity: number; price: number }[];
};
export type EstimateDetail = {
  id: number;
  totalPrice: number;
  status: string;
  expiresAt?: string;
  lines: { description: string; quantity: number; price: number }[];
};

export type Contact = { name: string; email: string; phone: string };
export type LeadInput = { name: string; phone: string; email: string; message?: string; source?: string };
