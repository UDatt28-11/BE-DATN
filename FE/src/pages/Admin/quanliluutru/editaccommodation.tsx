import React, { useEffect } from "react";
import { Modal, Form, Input, Select, message, Row, Col } from "antd";
import { Accommodation } from "../../../types/accommodation/accommodation";


interface Props {
    visible: boolean;
    accommodation: Accommodation | null;
    onCancel: () => void;
    onUpdate: (data: Accommodation) => void;
}

const { Option } = Select;

const EditAccommodation: React.FC<Props> = ({ visible, accommodation, onCancel, onUpdate }) => {
    const [form] = Form.useForm();

    useEffect(() => {
        if (accommodation) form.setFieldsValue(accommodation);
    }, [accommodation]);

    const handleSave = (values: any) => {
        if (accommodation) {
            const updated = { ...accommodation, ...values, updatedAt: new Date().toISOString() };
            onUpdate(updated);
            message.success("Cập nhật phòng thành công!");
            onCancel();
        }
    };

    return (
        <Modal visible={visible} title="Chỉnh sửa phòng" onCancel={onCancel} onOk={() => form.submit()}>
            <Form form={form} layout="vertical" onFinish={handleSave}>
                <Row gutter={16}>
                    <Col span={12}><Form.Item name="name" label="Tên phòng" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col span={12}><Form.Item name="type" label="Loại phòng" rules={[{ required: true }]}><Select placeholder="Chọn loại"><Option value="Phòng đôi">Phòng đôi</Option><Option value="Villa">Villa</Option><Option value="Căn hộ">Căn hộ</Option><Option value="Studio">Studio</Option><Option value="Phòng gia đình">Phòng gia đình</Option></Select></Form.Item></Col>
                </Row>
                <Form.Item name="price" label="Giá/đêm" rules={[{ required: true }]}><Input type="number" /></Form.Item>
                <Form.Item name="capacity" label="Sức chứa" rules={[{ required: true }]}><Input type="number" /></Form.Item>
                <Form.Item name="address" label="Địa chỉ" rules={[{ required: true }]}><Input /></Form.Item>
                <Form.Item name="manager" label="Người quản lý" rules={[{ required: true }]}><Input /></Form.Item>
                <Form.Item name="status" label="Trạng thái"><Select><Option value="Trống">Trống</Option><Option value="Đã đặt">Đã đặt</Option><Option value="Đang dùng">Đang dùng</Option><Option value="Bảo trì">Bảo trì</Option></Select></Form.Item>
            </Form>
        </Modal>
    );
};

export default EditAccommodation;
