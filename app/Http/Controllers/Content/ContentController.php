<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Samples;
use App\Models\UserVoices;
use getID3;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function scriptGeneratorOptions()
    {
        $response = Http::withHeaders([
            'Authorization' => 'BEARER eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxOTY1ODQsInRva2VuX3R5cGUiOiJhY2Nlc3NfdG9rZW4iLCJkZXZpY2VfaWQiOiJkZjg2NTNjZGE4Mzk4YjQ1IiwicGxhdGZvcm0iOiJhbmRyb2lkIiwiZXhwIjoxNjk1ODAxNTkxfQ.8rwJWCwJ-oUXJbD1K3w3t6_yBYeoy4dYO-uPflBdNgOXP8JqVQMlmPFRjnd5yqccSf3ZzAKVJHZDMlhv6pzfGw',

        ])->get('https://api-raven.clipp.ai/v1/ai_scripts_prompt_options');

        if ($response->successful()) {
            $data = $response->json(); // Convert the response to JSON

            return response()->json($data);
        } else {
            // Handle the error
            $statusCode = $response->status();
            $errorData = $response->json();
        }

    }

    public function scriptGenerator(Request $request)
    {
        $data = $request->validate([
            'script_tone' => 'required',
            'script_form' => 'required',
            'description' => 'required|max:1000',
        ]);
        $response = Http::withHeaders([
            'Authorization' => 'BEARER ' . env('SCRIPT_API_KEY'),

        ])->withBody(json_encode($data), 'application/json')
            ->post('https://api-raven.clipp.ai/v1/ai_scripts');

        if ($response->successful()) {
            $data = $response->json(); // Convert the response to JSON

            return response()->json($data);
        } else {
            // Handle the error
            $statusCode = $response->status();
            $errorData = $response->json();

            return response()->json($errorData, $statusCode);

        }

    }

    /**
     * Store a newly created resource in storage.
     */
    public function Generated(Request $request)
    {
        $data = $request->validate([
            'id' => 'required',
        ]);
        $id = $data['id'];
        $response = Http::withHeaders([
            'Authorization' => 'BEARER ' . env('SCRIPT_API_KEY'),

        ])->get("https://api-raven.clipp.ai/v1/ai_scripts/$id");

        if ($response->successful()) {
            $data = $response->json(); // Convert the response to JSON

            return response()->json($data);
        } else {
            // Handle the error
            $statusCode = $response->status();
            $errorData = $response->json();

            return response()->json($errorData, $statusCode);

        }

    }

    /**
     * Display the specified resource.
     */
    public function voices()
    {
        $jsonFilePath = public_path('img/voices.json');
        $jsonContent = file_get_contents($jsonFilePath);

        $response = Http::get('https://api.elevenlabs.io/v1/voices');

        if ($response->successful()) {
            $userId = Auth::user()->id;
            $userVoices = UserVoices::where('user_id', $userId)->get();

            return response()->json([
                'user' => $userVoices->load('sample'),
                "free" => json_decode($jsonContent),
                "Premium" => $response->json()['voices'],
            ]);
        } else {

            return response()->json($response->json(), $response->status());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function generateVoice(Request $request)
    {
        $data = $request->validate([
            'model_id' => 'required',
            'voice_id' => 'required',
            'text' => 'required|min:20',
        ]);
        $voice = UserVoices::where('voice_id', '=', $data['voice_id'])->first();
        if ($voice) {
            $voice->touch();
        }

        $response = Http::withHeaders([
            'xi-api-key' => env('ELEVEN_LABS_API_KEY'),
        ])->withBody(json_encode($data), 'application/json')
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$data['voice_id']}?optimize_streaming_latency=0");

        if ($response->successful()) {
            $audioData = $response->body();
            $fileName = 'AI-Studio-' . Str::random() . '.mp3'; // Provide a suitable file name
            Storage::put('public/audios/' . $fileName, $audioData); // Store the audio file

            $audioUrl = Storage::url('public/audios/' . $fileName); // Generate the URL for the stored file
            // Use getID3 library to get audio duration
            $audioFilePath = storage_path('app/public/audios/' . $fileName);

            $getID3 = new getID3();

            $audioFileInfo = $getID3->analyze($audioFilePath);

            $audioDuration = (float) $audioFileInfo['playtime_seconds'];

            // Dispatch a job to delete the audio file after a certain time interval (e.g., 24 hours)
            // (new DeleteAudioFile($fileName))->delay(Carbon::now()->addSeconds(90));

        } else {
            return response()->json(['message' => $response->json()], 500);
        }

        return response()->json(['audio_url' => $audioUrl, 'duration' => $audioDuration], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    // TODO Still Not finished
    /* TODO :
    1- Make file got from user phone
    2- assign it to the request
     */
    // public function cloneVoice(Request $request)
    // {
    //     // $filePath1 = public_path('storage/audios/AI-Studio-L4LUlekGwPCC8Kqd.mp3');
    //     $data = $request->validate([
    //         'audio_file' => 'required|mimes:mp3,wav,aac,m4a',
    //         'name' => 'required|string|max:20',

    //         'lang' => 'required|string',
    //     ]);
    //     if ($request->hasFile('audio_file') && $request->file('audio_file')->isValid()) {
    //         $file = $request->file('audio_file');
    //         $fileName = time() . '.' . $file->getClientOriginalExtension();
    //         $file->storeAs('audios', $fileName, 'public'); // Store in 'public/audio' directory
    //         // $customVoiceUrl = asset('storage/audios/' . $fileName);

    //         $filePath = Storage::disk('public')->path('audios/' . $fileName);

    //         // return response()->json(['file' => $filePath]);
    //         // return response()->json(['url' => $customVoiceUrl], 201);

    //         $response = Http::withHeaders([
    //             'accept' => 'application/json',
    //             'xi-api-key' => env('ELEVEN_LABS_API_KEY'),
    //         ])
    //             ->attach('files', file_get_contents($filePath), 'sample.mp3', ['Content-Type' => 'audio/mpeg'])
    //             ->post('https://api.elevenlabs.io/v1/voices/add', [
    //                 'name' => $request['name'],

    //                 'labels' => json_encode(['language' => $request['lang']]),
    //             ]);
    //         $dir = "public/audios/{$fileName}";
    //         if ($response->successful()) {
    //             $user = Auth::user();
    //             UserVoices::create([
    //                 'user_id' => $user->id,
    //                 'name' => $request['name'] ?? 'AI Studio',
    //                 'lang' => $request['lang'] ?? 'English',
    //                 'voice_id' => $response->json()['voice_id'],
    //             ]);
    //             Storage::delete($dir);
    //             return response()->json($response->json());
    //         } else {
    //             $responseData = $response->json()['detail']['message'];
    //             Storage::delete($dir);

    //             return response()->json(['message' => $responseData], $response->status());

    //         }
    //     }

    // }
    public function cloneVoice(Request $request)
    {
        // Validate the request data
        $data = $request->validate([
            'audio_file1' => 'required',
            'audio_file2' => '',
            'audio_file3' => '',
            'audio_file4' => '',
            'audio_file5' => '',
            'name' => 'required|string|max:20',
            'lang' => 'required|string',
        ]);
        if ($request->hasFile('audio_file1') && $request->file('audio_file1')->isValid()) {
            $files[] = $request->file('audio_file1');
            if ($request->hasFile('audio_file2')) {

                $files[] = $request->file('audio_file2');

            }

            if ($request->hasFile('audio_file3')) {

                $files[] = $request->file('audio_file3');
            }

            if ($request->hasFile('audio_file4')) {

                $files[] = $request->file('audio_file3');
            }

            if ($request->hasFile('audio_file5')) {

                $files[] = $request->file('audio_file3');
            }
            $fileNames = [];
            $filePaths = [];
            // return response()->json([
            //     'n' => count($files),

            // ]);

            foreach ($files as $file) {
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                sleep(1);
                $file->storeAs('audios', $fileName, 'public'); // Store in 'public/audio' directory
                // $customVoiceUrl = asset('storage/audios/' . $fileName);

                $filePath = Storage::disk('public')->path('audios/' . $fileName);
                $filePaths[] = $filePath;
                $fileNames[] = $fileName;

            }

            $mainResponse = Http::withHeaders([
                'accept' => 'application/json',
                'xi-api-key' => env('ELEVEN_LABS_API_KEY'),
            ]);

            foreach ($filePaths as $key => $filePath) {
                $fileContent = file_get_contents($filePath);

                $mainResponse = $mainResponse->attach('files', $fileContent, "sample{$key}.mp3", ['Content-Type' => 'audio/mpeg']);

            }
            $user = Auth::user();
            if ($user->letters_count >= 10000) {
                $user->letters_count -= 10000;
                $user->save();

            } else {
                return response()->json(['message' => 'Not have enough letters', 'c' => $user->letters_count], 400);

            }
            $response = $mainResponse
            // ->attach('files', file_get_contents(reset($filePaths)), "sample1.mp3", ['Content-Type' => 'audio/mpeg'])
            // ->attach('files', file_get_contents(end($filePaths)), "sample2.mp3", ['Content-Type' => 'audio/mpeg'])
                ->post('https://api.elevenlabs.io/v1/voices/add', [
                    'name' => $request['name'],

                    'labels' => json_encode(['language' => $request['lang']]),
                ]);
            if ($response->successful()) {
                $voiceId = $response->json()['voice_id'];
                $sampleResponse = Http::withHeaders([
                    'accept' => 'application/json',
                    'xi-api-key' => env('ELEVEN_LABS_API_KEY'), // Replace with your actual xi-api-key
                ])->get("https://api.elevenlabs.io/v1/voices/{$voiceId}");

                $voice = UserVoices::create([
                    'user_id' => $user->id,
                    'name' => $request['name'] ?? 'AI Studio',
                    'lang' => $request['lang'] ?? 'English',
                    'voice_id' => $voiceId,
                ]);
                if ($sampleResponse->successful()) {
                    $samples = $sampleResponse->json()['samples'];
                    foreach ($samples as $sample) {
                        Samples::create([
                            'user_voices_id' => $voice->id,
                            'sample_id' => $sample['sample_id'],
                        ]);
                    }
                }

                foreach ($fileNames as $fileName) {
                    $dir = "public/audios/{$fileName}";

                    Storage::delete($dir);
                }
                return response()->json($response->json());
            } else {
                $responseData = $response->json()['detail']['message'];
                // Storage::delete($dir);
                foreach ($fileNames as $fileName) {
                    $dir = "public/audios/{$fileName}";

                    Storage::delete($fileName);
                }

                return response()->json(['message' => $responseData], $response->status());

            }
        }

    }

    public function deleteCloneVoice(Request $request, string $id)
    {
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'xi-api-key' => env('ELEVEN_LABS_API_KEY'), // Replace with your actual xi-api-key
        ])
            ->delete("https://api.elevenlabs.io/v1/voices/{$id}"); // Replace <voice-id> with the actual voice ID

        if ($response->successful()) {
            // Request was successful, handle the response
            $voice = UserVoices::where('voice_id', '=', $id);

            $voice->delete();
            return response()->json([
                'message' => 'Deleted Successfully',
            ]);

        } else {
            // Request failed, handle the error
            return response()->json(['message' => 'Voice does not exist', 'detail' => $response->json()['detail']], $response->status());
            // Your code to handle the error response goes here
        }

    }

    public function editCloneVoice(Request $request, string $id)
    {
        // Validate the request data
        $data = $request->validate([
            'audio_file1' => 'required',
            'audio_file2' => '',
            'audio_file3' => '',
            'audio_file4' => '',
            'audio_file5' => '',
            'name' => 'required|string|max:20',
            'lang' => 'required|string',
        ]);
        if ($request->hasFile('audio_file1') && $request->file('audio_file1')->isValid()) {
            $files[] = $request->file('audio_file1');
            if ($request->hasFile('audio_file2')) {
                $files[] = $request->file('audio_file2');
            }

            if ($request->hasFile('audio_file3')) {

                $files[] = $request->file('audio_file3');
            }

            if ($request->hasFile('audio_file4')) {

                $files[] = $request->file('audio_file3');
            }

            if ($request->hasFile('audio_file5')) {

                $files[] = $request->file('audio_file3');
            }
            $fileNames = [];
            $filePaths = [];
            // return response()->json([
            //     'n' => count($files),

            // ]);

            foreach ($files as $file) {
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                sleep(1);
                $file->storeAs('audios', $fileName, 'public'); // Store in 'public/audio' directory
                // $customVoiceUrl = asset('storage/audios/' . $fileName);

                $filePath = Storage::disk('public')->path('audios/' . $fileName);
                $filePaths[] = $filePath;
                $fileNames[] = $fileName;

            }

            $mainResponse = Http::withHeaders([
                'accept' => 'application/json',
                'xi-api-key' => env('ELEVEN_LABS_API_KEY'),
            ]);

            foreach ($filePaths as $key => $filePath) {
                $fileContent = file_get_contents($filePath);

                $mainResponse = $mainResponse->attach('files', $fileContent, "sample{$key}.mp3", ['Content-Type' => 'audio/mpeg']);

            }
            $voice = UserVoices::where('voice_id', '=', $id)->first();
            $samples = Samples::where('user_voices_id', '=', $voice->id)->get();
            $voice->touch();

            foreach ($samples as $sample) {
                $sampleId = $sample->sample_id;
                $sampleDelete = Http::withHeaders([
                    'accept' => 'application/json',
                    'xi-api-key' => env('ELEVEN_LABS_API_KEY'), // Replace with your actual xi-api-key
                ])->delete("https://api.elevenlabs.io/v1/voices/{$id}/samples/{$sampleId}");
                if ($sampleDelete->successful()) {

                    $sample->delete();

                } else {
                    // Request failed, handle the error
                    $errorResponse = $sampleDelete->json();
                    // Your code to handle the error response goes here
                }

            }
            // return response()->json(['message' => 'samples deleted']);

            $response = $mainResponse
            // ->attach('files', file_get_contents(reset($filePaths)), "sample1.mp3", ['Content-Type' => 'audio/mpeg'])
            // ->attach('files', file_get_contents(end($filePaths)), "sample2.mp3", ['Content-Type' => 'audio/mpeg'])
                ->post("https://api.elevenlabs.io/v1/voices/{$id}/edit", [
                    'name' => $request['name'],

                    'labels' => json_encode(['language' => $request['lang']]),
                ]);
            if ($response->successful()) {

                // $voice = UserVoices::where('voice_id', '=', $id)->first();

                $voice->name = $request['name'];
                $voice->lang = $request['lang'];

                $sampleResponse = Http::withHeaders([
                    'accept' => 'application/json',
                    'xi-api-key' => env('ELEVEN_LABS_API_KEY'), // Replace with your actual xi-api-key
                ])->get("https://api.elevenlabs.io/v1/voices/{$id}");

                if ($sampleResponse->successful()) {
                    $samples = $sampleResponse->json()['samples'];
                    foreach ($samples as $sample) {
                        Samples::create([
                            'user_voices_id' => $voice->id,
                            'sample_id' => $sample['sample_id'],
                        ]);
                    }
                }

                $voice->save();
                foreach ($fileNames as $fileName) {
                    $dir = "public/audios/{$fileName}";

                    Storage::delete($dir);
                }
                return response()->json(['message' => 'Updated successfully']);
            } else {
                $responseData = $response->json()['detail']['message'];
                // Storage::delete($dir);
                foreach ($fileNames as $fileName) {
                    $dir = "public/audios/{$fileName}";

                    Storage::delete($fileName);
                }

                return response()->json(['message' => $responseData], $response->status());

            }
        }

    }

    public function getUserVoices()
    {
        $userId = Auth::user()->id;

        $voices = UserVoices::where('user_id', '=', $userId)->get();
        return response()->json([

            'voices' => $voices->load('sample'),
        ]);

    }

    public function simpleVoiceGeneration(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required',
                'speaker' => 'required',
                'text' => 'required',
            ]);

            $client = new Client(['timeout' => 1200]);

            $response = $client->post('https://apis.topmediai.com/tts', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'type' => $request['type'],
                    'speaker' => $request['speaker'],
                    'text' => "<speak>{$request['text']}</speak>",
                ],
            ]);

            $path = json_decode($response->getBody(), true)['data'];
            sleep(2);

            $finaRes = $client->get("https://apis.topmediai.com/tts/audios/{$path}");
            $audioData = $finaRes->getBody();
            $fileName = 'AI-Studio-' . Str::random() . '.wav';
            Storage::put('public/audios/' . $fileName, $audioData);

            // Use getID3 library to get audio duration
            $audioFilePath = storage_path('app/public/audios/' . $fileName);

            $getID3 = new getID3();
            $audioFileInfo = $getID3->analyze($audioFilePath);

            $audioDuration = (float) $audioFileInfo['playtime_seconds'];

            $audioUrl = Storage::url('public/audios/' . $fileName);

            return response()->json(['audio_url' => $audioUrl, 'duration' => $audioDuration], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()]);
        }
    }

    public function getCloneVoiceLast30Days()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Query and delete records older than 30 days
        $voices = UserVoices::where('updated_at', '<', $thirtyDaysAgo)->get();

        return response()->json([
            'data' => $voices,
        ]);
    }

    public function deleteCloneVoiceLast30Days()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Query and delete records older than 30 days
        $voices = UserVoices::where('updated_at', '<', $thirtyDaysAgo)->get();

        if ($voices) {
            foreach ($voices as $voice) {
                $response = Http::withHeaders([
                    'accept' => 'application/json',
                    'xi-api-key' => env('ELEVEN_LABS_API_KEY'), // Replace with your actual xi-api-key
                ])
                    ->delete("https://api.elevenlabs.io/v1/voices/{$voice['voice_id']}"); // Replace <voice-id> with the actual voice ID

                if ($response->successful()) {
                    // Request was successful, handle the response
                    $voice = UserVoices::where('voice_id', '=', $voice['voice_id']);

                    $voice->delete();

                    return response()->json([
                        'message' => 'Deleted Successfully',
                    ]);

                } else {
                    // Request failed, handle the error
                    return response()->json(['message' => 'Voice does not exist', 'detail' => $response->json()['detail']], $response->status());
                    // Your code to handle the error response goes here
                }

            }
        }

        return response()->json([
            'message' => 'No voices found',
        ], 400);
    }

    public function getSampleFromVoice(Request $request)
    {

        $request->validate([
            'voice_id' => 'required',
            'sample_id' => 'required',
        ]);

        $client = new Client();

        $voiceId = $request['voice_id'];
        $sampleId = $request['sample_id'];
// Define the API URL with placeholders for <voice-id> and <sample-id>
        $url = "https://api.elevenlabs.io/v1/voices/{$voiceId}/samples/{$sampleId}/audio";

// Define the headers
        $headers = [
            'accept' => 'audio/*',
            'xi-api-key' => env('ELEVEN_LABS_API_KEY'),
        ];

        try {
            // Send the GET request
            $response = $client->get($url, [
                'headers' => $headers,

            ]);

            // Get the audio content as a stream
            $audioContent = $response->getBody();

            // Set the appropriate content-type header for audio
            $headers = [
                'Content-Type' => 'audio/mpeg',
            ];

            $audioContentType = 'audio/mpeg'; // Change this to the appropriate audio format
            $audioContentLength = $audioContent->getSize();
            return Response::make($audioContent)
                ->header('Content-Type', $audioContentType)
                ->header('Content-Length', $audioContentLength);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle exceptions if the request fails
            // You can log the error or return an appropriate response
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

    }
}
