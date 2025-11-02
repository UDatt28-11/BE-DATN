import React from "react";
import { Modal, Row, Col, Tag } from "antd";
import { Accommodation } from "../../../types/accommodation/accommodation";


interface Props {
    visible: boolean;
    accommodation: Accommodation | null;
    onCancel: () => void;
}

const ViewAccommodation: React.FC<Props> = ({ visible, accommodation, onCancel }) => {
    return (
        <Modal visible={visible} title="Chi tiết phòng" footer={null} onCancel={onCancel} width={700}>
            {accommodation && (
                <Row gutter={[16,16]}>
                    <Col span={12}><b>Mã phòng:</b> #{accommodation.id}</Col>
                    <Col span={12}><b>Trạng thái:</b> <Tag>{accommodation.status}</Tag></Col>
                    <Col span={24}><b>Tên phòng:</b> {accommodation.name}</Col>
                    <Col span={12}><b>Loại phòng:</b> <Tag>{accommodation.type}</Tag></Col>
                    <Col span={12}><b>Sức chứa:</b> {accommodation.capacity || "N/A"}</Col>
                    <Col span={12}><b>Giá/đêm:</b> {accommodation.price.toLocaleString("vi-VN")}₫</Col>
                    <Col span={12}><b>Người quản lý:</b> {accommodation.manager}</Col>
                    <Col span={24}><b>Địa chỉ:</b> {accommodation.address}</Col>
                    {accommodation.description && <Col span={24}><b>Mô tả:</b> {accommodation.description}</Col>}
                    <Col span={24}><b>Cập nhật lần cuối:</b> {new Date(accommodation.updatedAt).toLocaleDateString("vi-VN")}</Col>
                </Row>
            )}
        </Modal>
    );
};

export default ViewAccommodation;
