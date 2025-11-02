import React from "react";
import { Modal } from "antd";
import { Amenity } from "../../../types/category/category";


interface Props {
    visible: boolean;
    onCancel: () => void;
    amenity: Amenity | null;
}

const VariantAmenity: React.FC<Props> = ({ visible, onCancel, amenity }) => {
    return (
        <Modal title={`Giá trị tiện ích: ${amenity?.name}`} open={visible} onCancel={onCancel} footer={null}>
            <p>Trang quản lý biến thể tiện ích (chưa triển khai chi tiết).</p>
        </Modal>
    );
};

export default VariantAmenity;
