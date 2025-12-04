<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Review;
use App\Models\ReviewLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewInteractionController extends Controller
{
    /**
     * Get comments for a review
     */
    public function getComments(Request $request, int $reviewId): JsonResponse
    {
        try {
            $review = Review::find($reviewId);
            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đánh giá.',
                ], 404);
            }

            $comments = Comment::where('review_id', $reviewId)
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->with([
                    'user:id,full_name,avatar',
                    'replies' => function ($query) {
                        $query->with('user:id,full_name,avatar')
                            ->orderBy('created_at', 'asc');
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $comments,
            ]);
        } catch (\Exception $e) {
            Log::error('ReviewInteractionController@getComments failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy bình luận.',
            ], 500);
        }
    }

    /**
     * Add a comment to a review (requires authentication)
     */
    public function addComment(Request $request, int $reviewId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string|min:1|max:1000',
                'parent_id' => 'nullable|exists:comments,id',
            ]);

            $review = Review::find($reviewId);
            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đánh giá.',
                ], 404);
            }

            // Validate parent comment belongs to same review
            if ($request->parent_id) {
                $parentComment = Comment::find($request->parent_id);
                if (!$parentComment || $parentComment->review_id != $reviewId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bình luận cha không hợp lệ.',
                    ], 400);
                }
            }

            $comment = Comment::create([
                'review_id' => $reviewId,
                'user_id' => $request->user()->id,
                'parent_id' => $request->parent_id,
                'content' => $request->content,
                'status' => 'active',
            ]);

            $comment->load('user:id,full_name,avatar');

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm bình luận thành công.',
                'data' => $comment,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ReviewInteractionController@addComment failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm bình luận.',
            ], 500);
        }
    }

    /**
     * Delete a comment (owner or admin only)
     */
    public function deleteComment(Request $request, int $commentId): JsonResponse
    {
        try {
            $comment = Comment::find($commentId);
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy bình luận.',
                ], 404);
            }

            // Check if user owns the comment
            if ($comment->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa bình luận này.',
                ], 403);
            }

            $comment->update(['status' => 'deleted']);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa bình luận thành công.',
            ]);
        } catch (\Exception $e) {
            Log::error('ReviewInteractionController@deleteComment failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa bình luận.',
            ], 500);
        }
    }

    /**
     * Toggle like/dislike on a review (requires authentication)
     */
    public function toggleLike(Request $request, int $reviewId): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|in:like,dislike',
            ]);

            $review = Review::find($reviewId);
            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đánh giá.',
                ], 404);
            }

            $userId = $request->user()->id;
            $type = $request->type;

            // Check existing like/dislike
            $existingLike = ReviewLike::where('review_id', $reviewId)
                ->where('user_id', $userId)
                ->first();

            if ($existingLike) {
                if ($existingLike->type === $type) {
                    // Remove like/dislike if same type
                    $existingLike->delete();
                    $action = 'removed';
                } else {
                    // Change type
                    $existingLike->update(['type' => $type]);
                    $action = 'changed';
                }
            } else {
                // Create new like/dislike
                ReviewLike::create([
                    'review_id' => $reviewId,
                    'user_id' => $userId,
                    'type' => $type,
                ]);
                $action = 'added';
            }

            // Get updated counts
            $likeCount = ReviewLike::where('review_id', $reviewId)->where('type', 'like')->count();
            $dislikeCount = ReviewLike::where('review_id', $reviewId)->where('type', 'dislike')->count();

            // Get user's current like status
            $userLike = ReviewLike::where('review_id', $reviewId)
                ->where('user_id', $userId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => $action === 'removed' 
                    ? 'Đã bỏ ' . ($type === 'like' ? 'thích' : 'không thích') 
                    : ($action === 'changed' 
                        ? 'Đã thay đổi thành ' . ($type === 'like' ? 'thích' : 'không thích')
                        : 'Đã ' . ($type === 'like' ? 'thích' : 'không thích') . ' đánh giá'),
                'data' => [
                    'like_count' => $likeCount,
                    'dislike_count' => $dislikeCount,
                    'user_reaction' => $userLike ? $userLike->type : null,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ReviewInteractionController@toggleLike failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra.',
            ], 500);
        }
    }

    /**
     * Get like status for reviews (for logged in user)
     */
    public function getLikeStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'review_ids' => 'required|array',
                'review_ids.*' => 'integer',
            ]);

            $userId = $request->user()->id;
            $reviewIds = $request->review_ids;

            $likes = ReviewLike::whereIn('review_id', $reviewIds)
                ->where('user_id', $userId)
                ->get()
                ->keyBy('review_id');

            $likeCounts = ReviewLike::whereIn('review_id', $reviewIds)
                ->where('type', 'like')
                ->selectRaw('review_id, count(*) as count')
                ->groupBy('review_id')
                ->pluck('count', 'review_id');

            $dislikeCounts = ReviewLike::whereIn('review_id', $reviewIds)
                ->where('type', 'dislike')
                ->selectRaw('review_id, count(*) as count')
                ->groupBy('review_id')
                ->pluck('count', 'review_id');

            $result = [];
            foreach ($reviewIds as $reviewId) {
                $result[$reviewId] = [
                    'like_count' => $likeCounts[$reviewId] ?? 0,
                    'dislike_count' => $dislikeCounts[$reviewId] ?? 0,
                    'user_reaction' => isset($likes[$reviewId]) ? $likes[$reviewId]->type : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('ReviewInteractionController@getLikeStatus failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra.',
            ], 500);
        }
    }
}
