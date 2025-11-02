import React from "react";
import { Modal, Descriptions, Tag, Space } from "antd";

import { CalendarOutlined, CheckCircleOutlined, ClockCircleOutlined, CloseCircleOutlined, ExclamationCircleOutlined, PhoneOutlined, HomeOutlined, UserOutlined, TeamOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import { Booking, BookingStatus } from "../../../types/booking/booking";

interface ViewBookingProps {
    booking: Booking | null;
    visible: boolean;
    onClose: () => void;
}

const ViewBooking: React.FC<ViewBookingProps> = ({ booking, visible, onClose }) => {
    if (!booking) return null;

    const getStatusConfig = (status: BookingStatus) => {
        const configs: Record<BookingStatus, { color: string; icon: React.ReactNode }> = {
            "Đang chờ": { color: "default", icon: <ClockCircleOutlined /> },
            "Đã xác nhận": { color: "processing", icon: <ExclamationCircleOutlined /> },
            "Đã thanh toán": { color: "success", icon: <CheckCircleOutlined /> },
            "Đã hủy": { color: "error", icon: <CloseCircleOutlined /> },
            "Hoàn thành": { color: "success", icon: <CheckCircleOutlined /> },
        };
        return configs[status];
    };

    return (
        <Modal visible={visible} title={`Chi tiết ${booking.id}`} onCancel={onClose} footer={null} width={700}>
            <Descriptions bordered column={2}>
                <Descriptions.Item label="Mã đặt phòng">{booking.id}</Descriptions.Item>
                <Descriptions.Item label="Trạng thái"><Tag color={getStatusConfig(booking.status).color}>{booking.status}</Tag></Descriptions.Item>
                <Descriptions.Item label="Khách hàng">{booking.customerName}</Descriptions.Item>
                <Descriptions.Item label="SĐT"><PhoneOutlined /> {booking.customerPhone}</Descriptions.Item>
                <Descriptions.Item label="Homestay">{booking.homestayName}</Descriptions.Item>
                <Descriptions.Item label="Check-in">{dayjs(booking.checkIn).format("DD/MM/YYYY")}</Descriptions.Item>
                <Descriptions.Item label="Check-out">{dayjs(booking.checkOut).format("DD/MM/YYYY")}</Descriptions.Item>
                <Descriptions.Item label="Số đêm">{booking.nights}</Descriptions.Item>
                <Descriptions.Item label="Số khách">{booking.guests}</Descriptions.Item>
                <Descriptions.Item label="Tổng tiền">{booking.totalPrice.toLocaleString()} VNĐ</Descriptions.Item>
                <Descriptions.Item label="Phương thức thanh toán">{booking.paymentMethod}</Descriptions.Item>
                <Descriptions.Item label="Nhân viên">{booking.staff}</Descriptions.Item>
                <Descriptions.Item label="Ghi chú">{booking.notes || "-"}</Descriptions.Item>
            </Descriptions>
        </Modal>
    );
};

export default ViewBooking;
