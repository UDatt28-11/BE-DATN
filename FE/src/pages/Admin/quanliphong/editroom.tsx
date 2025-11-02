import React, { useState, useEffect } from "react";
import { Modal, Form, Input, Switch, Row, Col, Upload, message } from "antd";
import { PlusOutlined } from "@ant-design/icons";
import { Listing } from "../../../types/room/room";
import { updateListing } from "../../../service/room";


interface EditRoomProps {
    visible: boolean;
    listing: Listing;
    onClose: () => void;
}

const EditRoom: React.FC<EditRoomProps> = ({ visible, listing, onClose }) => {
    const [form] = Form.useForm();
    const [fileList, setFileList] = useState<any[]>([]);

    useEffect(() => {
        if (listing) {
            form.setFieldsValue(listing);
            setFileList([{ uid: "-1", name: "image.png", status: "done", url: listing.image }]);
        }
    }, [listing]);

    const handleOk = () => {
        form.validateFields().then((values) => {
            updateListing({ ...listing, ...values, image: fileList[0]?.url || listing.image, updatedAt: new Date().toISOString().split("T")[0] });
            message.success("Cập nhật phòng thành công!");
            onClose();
        });
    };

    return (
        <Modal title="Chỉnh sửa phòng" open={visible} onCancel={onClose} onOk={handleOk}>
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

export default EditRoom;
