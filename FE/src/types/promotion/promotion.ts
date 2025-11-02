// src/pages/quanlimagiamgia/types/promotion.ts
export type PromotionStatus = "Đang hoạt động" | "Chưa áp dụng" | "Hết hạn" | "Vô hiệu hóa";
export type DiscountType = "Phần trăm" | "Số tiền cố định";

export interface Promotion {
    id: string;
    code: string;
    name: string;
    description: string;
    discountType: DiscountType;
    discountValue: number;
    minOrderValue: number;
    maxDiscount?: number;
    startDate: string;
    endDate: string;
    usageLimit: number;
    usedCount: number;
    status: PromotionStatus;
    applicableLocations: string[];
    createdAt: string;
    updatedAt: string;
}
