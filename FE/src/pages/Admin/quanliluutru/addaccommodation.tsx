import React from "react";
import { Modal, Form, Input, Select, message, Row, Col } from "antd";
import { Accommodation } from "../../../types/accommodation/accommodation";


interface Props {
    visible: boolean;
    onCancel: () => void;
    onAdd: (data: Accommodation) => void;
}

const { Option } = Select;

const AddAccommodation: React.FC<Props> = ({ visible, onCancel, onAdd }) => {
    const [form] = Form.useForm();

    const handleSave = (values: any) => {
        const newAccommodation: Accommodation = {
            id: Date.now(),
            ...values,
            status: "Trống",
            updatedAt: new Date().toISOString(),
        };
        onAdd(newAccommodation);
        form.resetFields();
        message.success("Thêm phòng mới thành công!");
        onCancel();
    };

    return (
        <Modal visible={visible} title="Thêm phòng mới" onCancel={onCancel} onOk={() => form.submit()}>
            <Form form={form} layout="vertical" onFinish={handleSave}>
                <Row gutter={16}>
                    <Col span={12}><Form.Item name="name" label="Tên phòng" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col span={12}><Form.Item name="type" label="Loại phòng" rules={[{ required: true }]}><Select placeholder="Chọn loại"><Option value="Phòng đôi">Phòng đôi</Option><Option value="Villa">Villa</Option><Option value="Căn hộ">Căn hộ</Option><Option value="Studio">Studio</Option><Option value="Phòng gia đình">Phòng gia đình</Option></Select></Form.Item></Col>
                </Row>
                <Form.Item name="price" label="Giá/đêm" rules={[{ required: true }]}><Input type="number" /></Form.Item>
                <Form.Item name="capacity" label="Sức chứa" rules={[{ required: true }]}><Input type="number" /></Form.Item>
                <Form.Item name="address" label="Địa chỉ" rules={[{ required: true }]}><Input /></Form.Item>
                <Form.Item name="manager" label="Người quản lý" rules={[{ required: true }]}><Input /></Form.Item>
            </Form>
        </Modal>
    );
};

export default AddAccommodation;
