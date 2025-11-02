import React from "react";
import { Modal, Form, Input, Select, message } from "antd";
import { Amenity } from "../../../types/amenity/amenity";


interface Props {
    visible: boolean;
    onCancel: () => void;
    onAdd: (data: Amenity) => void;
}

const AddAmenity: React.FC<Props> = ({ visible, onCancel, onAdd }) => {
    const [form] = Form.useForm();

    const handleOk = () => {
        form.validateFields().then(values => {
            const newAmenity: Amenity = {
                id: Math.floor(Math.random()*10000),
                ...values,
                createdAt: new Date().toISOString().split("T")[0],
                updatedAt: new Date().toISOString().split("T")[0]
            };
            onAdd(newAmenity);
            message.success("Thêm tiện ích thành công!");
            form.resetFields();
            onCancel();
        });
    };

    return (
        <Modal title="Thêm tiện ích mới" open={visible} onOk={handleOk} onCancel={onCancel} okText="Lưu" cancelText="Hủy">
            <Form layout="vertical" form={form}>
                <Form.Item name="name" label="Tên tiện ích" rules={[{ required:true, message:"Vui lòng nhập tên tiện ích" }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="icon" label="Biểu tượng">
                    <Input placeholder="Ví dụ: 🏊, 🍳, 📶..." />
                </Form.Item>
                <Form.Item name="type" label="Loại" rules={[{ required:true }]}>
                    <Select>
                        <Select.Option value="Cơ bản">Cơ bản</Select.Option>
                        <Select.Option value="Nâng cao">Nâng cao</Select.Option>
                    </Select>
                </Form.Item>
                <Form.Item name="description" label="Mô tả">
                    <Input.TextArea rows={3} />
                </Form.Item>
                <Form.Item name="status" label="Trạng thái" initialValue="Hoạt động">
                    <Select>
                        <Select.Option value="Hoạt động">Hoạt động</Select.Option>
                        <Select.Option value="Ẩn">Ẩn</Select.Option>
                    </Select>
                </Form.Item>
            </Form>
        </Modal>
    );
};

export default AddAmenity;
