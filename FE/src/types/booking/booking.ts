export type BookingStatus = "Đang chờ" | "Đã xác nhận" | "Đã thanh toán" | "Đã hủy" | "Hoàn thành";

export interface Booking {
    id: string;
    customerName: string;
    customerPhone: string;
    customerEmail: string;
    homestayName: string;
    homestayId: number;
    checkIn: string;
    checkOut: string;
    nights: number;
    guests: number;
    totalPrice: number;
    status: BookingStatus;
    createdAt: string;
    staff: string;
    paymentMethod: string;
    notes?: string;
    cancellationReason?: string;
}
