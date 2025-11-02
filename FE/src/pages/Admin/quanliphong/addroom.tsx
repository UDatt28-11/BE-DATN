import React, { useState } from "react";
import { Modal, Form, Input, Button, message, Upload, Switch, Row, Col } from "antd";
import { PlusOutlined } from "@ant-design/icons";


import { Listing } from "../../../types/room/room";
import { addListing } from "../../../service/room";

interface AddRoomProps {
    visible: boolean;
    onClose: () => void;
}

const AddRoom: React.FC<AddRoomProps> = ({ visible, onClose }) => {
    const [form] = Form.useForm();
    const [fileList, setFileList] = useState<any[]>([]);

    const handleOk = () => {
        form.validateFields().then((values) => {
            const newListing: Listing = {
                key: Date.now().toString(),
                id: Date.now(),
                status: "available",
                verified: values.verified || false,
                image: fileList[0]?.url || "https://via.placeholder.com/150",
                createdAt: new Date().toISOString().split("T")[0],
                updatedAt: new Date().toISOString().split("T")[0],
                ...values,
            };
            addListing(newListing);
            message.success("Thêm phòng mới thành công!");
            form.resetFields();
            setFileList([]);
            onClose();
        });
    };

    return (
        <Modal title="Thêm phòng mới" open={visible} onCancel={onClose} onOk={handleOk}>
            <Form form={form} layout="vertical">
                <Form.Item name="name" label="Tên phòng" rules={[{ required: true }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="location" label="Địa điểm" rules={[{ required: true }]}>
                    <Input />
                </Form.Item>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="price" label="Giá" rules={[{ required: true }]}>
                            <Input type="number" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="rating" label="Đánh giá">
                            <Input type="number" min={0} max={5} step={0.1} />
                        </Form.Item>
                    </Col>
                </Row>
                <Form.Item label="Hình ảnh">
                    <Upload listType="picture-card" fileList={fileList} beforeUpload={() => false} onChange={({ fileList }) => setFileList(fileList)}>
                        {fileList.length === 0 && <div><PlusOutlined /><div>Tải ảnh</div></div>}
                    </Upload>
                </Form.Item>
                <Form.Item name="verified" label="Xác minh" valuePropName="checked">
                    <Switch />
                </Form.Item>
            </Form>
        </Modal>
    );
};

export default AddRoom;
