export interface Accommodation {
    id: number;
    name: string;
    status: "Trống" | "Đã đặt" | "Đang dùng" | "Bảo trì";
    price: number;
    type: string;
    manager: string;
    updatedAt: string;
    address?: string;
    capacity?: number;
    description?: string;
    amenities?: string[];
}
